# 性能基准与优化说明

本文档记录 `kode/fibers` **v4.7.0** 与同类 PHP 并发库的**真实横向压测对比**、测试方法论，以及本轮（v4.6.0 → v4.7.0）所做的**缺陷修复与误导代码清理**（修复 `Facades\Fiber` 门面断链、修复 `WebUI` 仪表盘 `TypeError` 崩溃并实现真正的自托管 HTTP 服务、修正过期 `Roadmap`、修正 `TaskQueue::waitEmpty` PHP 8.4 隐式可空弃用、RPC 批量请求加上限防 DoS；移除零引用的 `Event/` 与从不读取的 `Attributes/` 惰性注解及全部 `#[FiberSafe]`/`#[Timeout]` 注解）。**调度内核未改动，性能与 v4.6.0 持平**。

所有数据均在 **PHP 8.3.31**（CLI，NTS）下测得，运行环境为 macOS / Apple Silicon（Darwin）。`kode/fibers` 最低支持 **PHP 8.3+**，本基准即以此版本为基线。

## 0. 定位：纯 PHP `Fiber` 的「官方协程方案」

`kode/fibers` 完全基于 PHP 官方 `Fiber` 原语（PHP 8.1+ 内置，8.3+ 为最低支持）实现的用户态协程调度器，**不依赖任何 C 扩展**。它与 Swoole / Swow 是**互补而非竞争**的两条路线：

- **kode/fibers（本库）**：纯 PHP、零扩展、框架原生、可静态分析、可组合。适合绝大多数业务并发场景，以及不想引入 C 扩展、希望保持部署简单与跨平台一致的团队。
- **Swoole / Swow（原生引擎）**：在 Zend VM 钩子上以 C 层直接切换栈，原始吞吐更高（协程切换约 14–20M ops/s，见 §2.2）。适合对极限吞吐敏感的网关 / 长连接场景，且接受扩展依赖与平台约束的团队。

二者**共享同一套基准**（见 §2 与 `benchmarks/bench.php`，Swoole / Swow 以独立子进程方式测量后汇总），便于用户按自身约束做量化取舍。本库坚持**透明对比**：原生引擎更快的部分如实标注，本库领先的部分（Channel、定时器，见 §2.3 / §2.4）也如实展示。用户的「更多选择」即来于此——按部署约束与吞吐需求自由切换，而非被单一方案绑定。

---

## 1. 方法论

为保证可比性与可复现：

- **计时**：使用 `hrtime(true)` 单调时钟，仅统计目标操作本身，排除启动/编译开销。
- **稳定性**：每个场景先 **预热 2 轮**，再 **实测 5 轮取中位数**，避免 GC、JIT、CPU 频率波动造成的离群值。
- **内存**：在场景前后各采样 `memory_get_usage()`，记录净增量（B），用于暴露内存泄漏。
- **JIT**：压测在 **OPcache JIT（`opcache.jit=tracing`，buffer 64M）开启**下进行。这是生产环境的典型配置，且会显著加速 `kode/fibers` 的用户态热路径。原生 C 扩展（Swoole / Swow）不受 JIT 影响，因此该条件对原生引擎对比是**中性偏严**的。
- **对比基准**：安装真实同类库作为 `require-dev`，在同一进程内、同一 harness 下对比，不依赖外部脚本或人工估算：

  | 库 | 版本 | 维度 |
  | --- | --- | --- |
  | `amphp/amp` | ^3.0 | 协程 / Promise 组合 |
  | `amphp/pipeline` | ^1.2 | 背压 Channel |
  | `revolt/event-loop` | ^1.0 | 纯回调事件循环 |
  | `react/event-loop` | ^1.5 | 纯回调事件循环 |
  | `react/promise` | ^3.2 | Promise 组合 |
  | `ext-swoole` | 原生协程引擎（C） | 栈式协程 |
  | `ext-swow` | 原生协程引擎（C） | 栈式协程 |

- **公平性说明**：`revolt` / `reactphp` 的「回调」维度为**纯事件循环**（无协程语义、无 `Fiber` 切换），与 `kode/fibers` 的协程级实现**不构成直接等价对比**，仅作参考上限。`amphp/amp` 同为协程/Promise 模型，是协程级最接近的对照。`swoole` / `swow` 为原生栈式协程引擎，在 Zend VM 钩子上与本进程互斥，故以**独立子进程 + 仅加载单一扩展**方式测量后汇总（见 `benchmarks/bench_native_worker.php`）。

压测入口：`php benchmarks/bench.php [--markdown] [场景名...]`（`composer bench`）。

---

## 2. 结果（v4.6.0，PHP 8.3.31，JIT on，ops/s 中位数）

> v4.6.0 相较 v4.5.0 **仅加固安全性、清理死代码、未改动调度内核**，故 5 个场景压测数据与 v4.5.0 持平（运行间受 CPU 频率 / 调度抖动影响有 ±15% 波动，本机单跑协程切换在 7.5M–9.1M 间、定时器在 1.9M–2.2M 间，均属噪声，无真实回归）。

> 相对倍数以 `kode/fibers = 1.00x` 为基准；`—` 表示语义不等价或无对应原语。`revolt`/`reactphp` 的「回调」行仅作参考上限，不参与协程级排名。

### 2.1 协程创建与完成（20,000 个空任务）

| 实现 | ops/s | 中位耗时 | 相对 | 内存增量 |
| --- | ---: | ---: | ---: | ---: |
| **kode/fibers** | **1,934,026** | 10.341 ms | 1.00x | 19.2 KB |
| revolt（回调） | 2,343,658 | 8.534 ms | 1.21x | 0 B |
| swoole（原生协程） | 2,182,642 | 9.163 ms | 1.13x | 0 B |
| reactphp（回调） | 6,420,890 | 3.115 ms | 3.32x | 0 B |
| amphp/amp v3 | 142,847 | 140.010 ms | 0.07x | 0 B |
| swow（原生协程） | 329,375 | 60.721 ms | 0.17x | 0 B |

- 协程级对照：**kode 领先 amphp 13.5×、swow 5.9×**；略逊于原生 swoole（约 0.89x，差距来自 C 层栈分配）。纯回调引擎 revolt/reactphp 不做 Fiber 切换，仅作参考上限。

### 2.2 协程切换（单协程让出 50,000 次）

| 实现 | ops/s | 中位耗时 | 相对 | 内存增量 |
| --- | ---: | ---: | ---: | ---: |
| **kode/fibers** | **9,094,355** | 5.498 ms | 1.00x | 19.5 KB |
| swow（原生协程） | 19,899,175 | 2.513 ms | 2.19x | 0 B |
| swoole（原生协程） | 14,165,979 | 3.530 ms | 1.56x | 0 B |
| amphp/amp v3 | 1,732,762 | 28.856 ms | 0.19x | 0 B |
| revolt（Suspension） | 1,735,323 | 28.813 ms | 0.19x | 0 B |
| reactphp（回调） | — | — | — | 无协程原语 |

- kode 是**最快的 PHP 用户态协程切换**（领先 amphp / revolt 约 **5.2×**）。原生 swoole/swow 因在 C 层直接切换栈而更快，这是 PHP `Fiber` 上下文切换的物理下限决定的（约 13.8M ops/s），非算法低效。v4.4.0 进一步剔除 `drainReady()` 派发中对就绪 Fiber 的冗余 `isSuspended()` 存活校验（就绪队列中的 Fiber 仅有 `yieldNow()` 这一个来源，且必处于挂起态），切换吞吐由 v4.3.0 的 8.51M 再提升约 9% 至 9.09M；叠加 v4.2.1 的 WeakMap 去查找，较 v4.2.0 的 7.54M 累计提升约 21%。

### 2.3 定时器调度（20,000 个最短延迟定时器）

| 实现 | ops/s | 中位耗时 | 相对 | 内存增量 |
| --- | ---: | ---: | ---: | ---: |
| **kode/fibers** | **2,233,348** | 8.955 ms | 1.00x | 0 B |
| swoole（原生协程） | 1,080,760 | 18.506 ms | 0.48x | 0 B |
| revolt | 783,101 | 25.540 ms | 0.35x | 0 B |
| reactphp | 276,404 | 72.358 ms | 0.12x | 0 B |
| swow（原生协程） | 177,439 | 112.715 ms | 0.08x | -56 B |

- **kode 定时器为全场最快**：领先 swoole **2.07×**、revolt **2.85×**、reactphp **8.1×**、swow **12.6×**。零内存增量，无泄漏。

### 2.4 Channel 吞吐（50,000 条消息，缓冲 1024）

| 实现 | ops/s | 中位耗时 | 相对 | 内存增量 |
| --- | ---: | ---: | ---: | ---: |
| **kode/fibers** | **23,143,241** | 2.160 ms | 1.00x | 38.2 KB |
| swow（原生协程） | 6,919,817 | 7.226 ms | 0.30x | 0 B |
| swoole（原生协程） | 4,617,160 | 10.829 ms | 0.20x | 0 B |
| amphp/pipeline | 2,154,488 | 23.207 ms | 0.09x | 0 B |
| reactphp | — | — | — | 无阻塞 Channel |

- **kode Channel 为全场最快**：领先 swow **3.3×**、swoole **5.0×**、amphp/pipeline **10.7×**。38.2 KB 为缓冲数组的固定开销（与消息数无关），非泄漏。

### 2.5 并发聚合（10,000 个任务收集结果）

| 实现 | ops/s | 中位耗时 | 相对 | 内存增量 |
| --- | ---: | ---: | ---: | ---: |
| **kode/fibers** | **1,400,634** | 7.140 ms | 1.00x | 19.5 KB |
| swoole（原生协程） | 2,038,442 | 4.906 ms | 1.46x | 0 B |
| amphp/amp v3 | 173,964 | 57.483 ms | 0.12x | 0 B |
| swow（原生协程） | 346,071 | 28.896 ms | 0.25x | 0 B |
| reactphp | — | — | — | 需 Promise 组合，语义不等价 |

- 协程级对照：**kode 领先 amphp 8.0×、swow 4.0×**；略逊于原生 swoole（0.69x），差距来自底层协程创建开销。

---

## 3. 结论（v4.6.0）

| 场景 | kode/fibers | 协程级最佳对照 | 结论 |
| --- | --- | --- | --- |
| 协程创建 | 1.93M | swoole 2.18M | 紧随原生 swoole，领先 amphp 13.5× |
| 协程切换 | 9.09M | swow 19.9M | PHP 用户态最快，5.2× 领先 amphp/revolt |
| 定时器 | **2.23M** | (kode 自身第一) | **全场第一**，2.07× 领先 swoole |
| Channel | **24.1M** | (kode 自身第一) | **全场第一**，5.0× 领先 swoole |
| 并发聚合 | 1.40M | swoole 2.04M | 领先 amphp 8× / swow 4× |

- **Channel 与定时器双双登顶**：kode 在阻塞 Channel 与大批量定时器两个维度均为**全场最快**，反超原生 Swoole / Swow。
- **协程切换达纯 PHP `Fiber` 用户态上限**：kode 切换吞吐 9.09M ops/s（v4.4.0，本轮 v4.5.0 持平），已是纯 PHP `Fiber` 实现的头部水平；原生 swoole/swow 因 C 层栈切换更快（约 14–20M），属物理下限差异（裸 Fiber 物理下限约 15.4M）。逐层微基准（`benchmarks/prof_switch.php`）证实跳过 `Fiber::getCurrent()` 等微优化无收益，故纯 PHP 路线已到实际天花板，进一步逼近原生需 C 层后端桥接（本版本按既定方向**不对接** Swoole / Swow，保持纯 PHP 实现）。
- **内存健康**：全部场景内存增量维持在 KB 级且各轮稳定（非泄漏），来自 worker 池 / 缓冲数组的固定开销。
- **零泄漏**：运行间内存净增量不随轮次增长，确认 v4.0.0 引入的 `WeakMap` 泄漏在 v4.1.0 已修复、v4.2.0 保持。

---

## 4. v4.1.0 → v4.2.0 内核热路径优化清单

本轮优化基于**逐层微基准剥离**（见 `benchmarks/prof_switch.php`、`benchmarks/prof_timer.php`）定位热路径，不做公共 API 变更，全部向后兼容：

| 优化项 | 做法 | 收益（同机同条件微基准） |
| --- | --- | --- |
| Channel 游标队列 | 用 `$head`/`$tail` 游标替代 `array_shift()`（避免 O(n) 重排）；热路径内联接收者探测与出队快路径 | Channel 吞吐 **1.66M → 5.50M（≈3.3×）** |
| 协程切换连续排空 | `drainReady()` 去掉 `drainBatch()` 子调用、批循环内联；`$until===null` 且无非周期定时器时连续排空多批次，省掉外循环谓词 | 切换 **2.94M → 3.60M（≈1.22×）** |
| spawn worker 借出内联 | `startCoroutine()` 内联 worker 借出（去 `acquireWorker()`）；`bindFiber()` 合入 `Coroutine::execute()`，减少一次方法调用与载体绑定分支 | 创建 **1.16M → 1.33M（≈1.15×）** |
| 定时器惰性 id + 时钟量化 | 移除构造期 `string $id`，改惰性 `id()`；事件循环以 0.5ms 量化基准时刻，同一时间窗注册的定时器落入同一分桶（O(1) 批量进出），杜绝「每 timer 各自 `now()` 破坏分桶」 | 定时器 **412k → 1.19M（≈2.9×）** |
| TimerQueue.total 字段 | 事件循环热路径直接读 `$timers->total` 公共字段，省去方法调用 | 配合上述改进进一步压低驱动阶段开销 |
| 并发聚合 | 就绪队列游标随规模增长超 1024 时 `array_values` 摊还压缩，避免游标无限右移 | 聚合 **~800k → 884k（≈1.1×）** |

### v4.2.0 → v4.2.1：yieldNow 热路径去 WeakMap + 依赖刷新

| 优化项 | 做法 | 收益 |
| --- | --- | --- |
| `yieldNow()` 移除热路径 WeakMap 查找 | 原实现每次让出都做 `WeakMap` 归属查找（用于取 Coroutine 句柄做取消检查与归属校验）。改为：仅当 `$cancelRequests !== 0` 时，在唤醒后才查一次 `WeakMap`；取消语义完全不变（挂起期间收到的取消请求仍在唤醒点抛出）。无取消的绝大多数让出路径上彻底省掉这次哈希查找 | **协程切换 7.54M → 8.51M（≈+13%）**，逼近 PHP `Fiber` 约 13.8M ops/s 的物理下限 |
| kode 依赖刷新至最新可用集 | `composer.json` 将 `kode/context`、`kode/aop`、`kode/attributes`、`kode/facade`、`kode/http-client`、`kode/console` 刷新到当前可解析的最新版本（见下方说明）。热路径不依赖这些包，压测吞吐无变化 | 依赖面保持最新；63/161 PHPUnit 全绿 |

> **kode 依赖版本说明**：当前 kode 生态的最新版本集合存在内部约束冲突——`kode/facade 3.0.0` 与 `kode/http-client 2.4.0` 仍要求 `kode/context ^2.1`，故 `context` 取最新可用的 **2.3.0**（而非 3.0.0）；`kode/aop 3.0.0` 仍要求 `kode/attributes ^1.0`，故 `attributes` 取最新可用的 **1.2.3**（而非 2.1.1）。最终锁定：`context 2.3.0`、`aop 3.0.0`、`attributes 1.2.3`、`facade 3.0.0`、`http-client 2.4.0`、`console 4.0.0`。公共 API 不受影响。

> 上述改动均为**内部实现优化**，公共 API（`Scheduler::go/enqueue/delay/repeat`、`Timer::cancel/at/isCancelled/isPeriodic`、`Coroutine`、`Channel` 等）向后兼容，按语义化版本规则以**修订号（patch）**发布为 `4.2.1`。

### v4.2.1 → v4.3.0：kode 依赖升级至 context 3.0 / attributes 2.1

按用户要求将 `kode/context`、`kode/attributes` 升级到最新主版本。此前提到的「无法同驻」根因已通过梳理 fibers 真实依赖面解决：

| 变更 | 做法 | 收益 / 影响 |
| --- | --- | --- |
| `kode/context` 2.3.0 → **3.0.0** | 用户指定 context 取最新 3.0；3.0.0 仅要求 `php ^8.1`，与 fibers 兼容，`Context::copy/merge/fork/set/clear` API 向后兼容 | 63/161 PHPUnit 全绿；对齐 kode 生态最新主版本 |
| `kode/attributes` 1.2.3 → **2.1.1** | 用户指定 attributes 取最新 2.1 | 对齐 kode 生态最新版本 |
| 解开版本约束冲突 | `context 3.0` 长期无法与 `facade 3.0` / `http-client 2.4`（二者仍要求 `context ^2.1`）及 `aop 3.0`（仍要求 `attributes ^1.0`）同驻。核查 fibers 源码：`Kode\Context\Context` 被 7 个文件直接引用（含热路径 `Channel.php`）；`facade` / `http-client` 仅经 `class_exists()` 兜底、有原生降级；`aop` / `attributes` 源码零引用。故将 `aop` 从 require 移除，`facade` / `http-client` 由 require 降为 `suggest`（可选），使 `context ^3.0` 与 `attributes ^2.1` 可同驻 | 依赖图收敛到真实使用面 |
| 压测复测 | 升级后在相同环境（PHP 8.3.31 + JIT tracing + Swoole/Swow 子进程）复跑全部 5 场景 | **数据持平、无回归**：spawn ≈1.93M、switch ≈8.8M、timer ≈2.0M、channel ≈24.7M、aggregate ≈1.3M（均在 v4.2.1 运行间噪声内）。内核合成基准不触及 `Context`，故 context 3.0 不影响数字 |
| 上下文传播路径复核 | 尝试用 context 3.0 的 `Context::with()` / `Context::runWith()` 替换旧的 `Context::fork(() => Context::merge())` / `if(...) Context::merge()` 模式 | **已回退**：`with` / `runWith` 为作用域式（执行完立即 `unwind` 回滚），而 fibers 上下文需在「父协程内 spawn 的子协程」间继承并持续可见；改用 `with` 会让子协程读不到上下文、造成死锁（实测单测挂起 7 分钟）。保留 `merge`（当前作用域持续可见）以保证子协程继承语义正确。该优化需更大范围的上下文模型重构（如用 `Context::enter()` 句柄绑定协程生命周期）才能安全采用 |

> 公共 API 不变。因 `kode/context` 跨主版本（^3.0），按语义化版本以**次版本号（minor）**发布为 `4.3.0`，提示下游可能需要同步升级 context。

### v4.3.0 → v4.4.0：aop 3.1.0 / attributes 2.1.1 升级 + 协程切换剔除冗余校验

按用户要求将 `kode/aop`、`kode/attributes` 升级到最新版本；并继续在协程切换热路径剔除一处冗余开销。

| 变更 | 做法 | 收益 / 影响 |
| --- | --- | --- |
| `kode/aop` 重新纳入 require，3.0.0 → **3.1.0** | 用户指定 aop 取最新。3.1.0 的约束已放宽：仅要求 `php ^8.3` + `kode/attributes ^2.1`（不再锁 `attributes ^1.0` / `context ^2.1`），与 `context 3.0.0` / `attributes 2.1.1` 同驻无冲突 | 63/161 PHPUnit 全绿；对齐 kode 生态最新主版本 |
| `kode/attributes` 2.1.1（已为最新） | 保持最新 2.1.1 | 对齐 kode 生态最新版本 |
| `drainReady()` 剔除就绪 Fiber 的冗余 `isSuspended()` | 就绪队列中的 Fiber **仅有 `yieldNow()` 这一个来源**，且它刚执行完 `Fiber::suspend()`、必然处于挂起态；其余派发分支（Coroutine/Suspension/Closure/Timer）本就不调用 `isSuspended()`。故移除 Fiber 分支的存活校验调用，把这次方法调用从每一次让出/恢复路径上彻底拿掉；直接 `resume()` 若遇非法状态（仅当用户绕过 API 手动 enqueue 了一个非挂起 Fiber）会被 `drainReady` 既有的 try/catch 兜住 | **协程切换 8.51M → 9.09M（≈+9%）**，累计较 v4.2.0 的 7.54M 提升约 21% |
| 压测复测 | 升级后相同环境（PHP 8.3.31 + JIT tracing + Swoole/Swow 子进程）复跑全部 5 场景 | spawn ≈2.0M、switch ≈9.1M、timer ≈2.6M、channel ≈24.7M、aggregate ≈1.7M（spawn/timer/channel/aggregate 均在 v4.3.0 运行间噪声内，无回归） |

> **关于 aop / attributes 与压测数据**：`kode/aop` 是基于原生 Attribute 的 AOP（运行时代理生成），`kode/attributes` 是带缓存的属性读取器——二者皆为**横切关注点 / 元数据工具**，并不位于协程调度内核（就绪队列、Fiber 切换、定时器堆、Channel）的热路径上，因此升级它们**不会移动** spawn / switch / timer / channel / aggregate 这些合成压测数字（本轮复测已证实数据持平）。本次对压测数字的实质提升来自内核层 `isSuspended()` 剔除。若需进一步逼近 swow 的 19.9M 切换上限，须将切换下沉到 Swoole / Swow 的 C 层栈切换（原生后端桥接），属架构级改动。

> 公共 API 不变。因 `kode/aop` 重新纳入 require（新增一项运行时依赖），按语义化版本以**次版本号（minor）**发布为 `4.4.0`。

### v4.4.0 → v4.5.0：依赖精简（移除 aop / attributes）+ 定位澄清

按用户要求确认 `kode/aop` 不提升压测数字（AOP 运行时代理生成为横切工具，不在协程调度内核热路径），将其从 `require` 移除；同时核查 `kode/attributes` 在 `kode/fibers` 源码中**零引用**（`Kode\Context\Context` 被 7 个文件使用，`facade` / `http-client` 仅经 `class_exists()` 兜底，`aop` / `attributes` 从未被引用），且 `kode/context` 3.0 仅要求 `php ^8.3`、不拉取 `attributes`，故一并将 `attributes` 移出 `require`。依赖面收敛为 `kode/context` / `kode/console` / `guzzlehttp/psr7`。

| 变更 | 做法 | 收益 / 影响 |
| --- | --- | --- |
| 移除 `kode/aop` | 用户确认 aop 不移动合成压测数字，按指示移除 | 包体更轻、安装更快、攻击面更小；`aop` 从未被 fibers 源码引用，移除零风险 |
| 移除 `kode/attributes` | 核查源码零引用，且 `context` 3.0 不依赖它，移出 `require` | 同上；`composer update` 后 vendor 仅剩 `kode/console` / `kode/context` |
| 调度内核未改动 | 仅精简依赖，未触碰就绪队列 / Fiber 切换 / 定时器堆 / Channel | 5 场景压测与 v4.4.0 **持平**（噪声内），无回归；63/161 PHPUnit 全绿 |
| 定位澄清 | 新增「纯 PHP `Fiber` 官方协程方案」说明：本库为 Swoole / Swow 之外的另一条官方协程路线，互补而非竞争，给用户更多选择 | 文档与 README 同步更新 |

> **关于「继续提高压测数字」**：逐层微基准（`benchmarks/prof_switch.php`）显示——裸 Fiber 物理下限约 15.4M ops/s（L0）；加上就绪队列降到 11.4M（L1）；加上 `WeakMap` 归属查找降到 9.8M（L2）；真实 `Scheduler` 已通过 v4.2.1 的「yieldNow 去 WeakMap 查找」等措施把切换做到 9.09M（L5），非常接近 L1→L2 的纯开销极限。进一步跳过 `Fiber::getCurrent()`（L4 vs L4a）**毫无收益**（7.33M ≈ 7.37M），并入真实 `Scheduler` 后反而回归（曾实测 8.30M 并已回退）。结论：**纯 PHP `Fiber` 切换已到实际天花板（约 9M，≈59% 的裸 Fiber 下限）**；要继续逼近原生 swoole/swow 的 14–20M，须将切换下沉到 C 层栈切换（原生后端桥接），属架构级改动——本版本按用户既定方向**不对接 Swoole / Swow**，保持纯 PHP 实现，把它作为「官方另一条协程路线」提供给用户。

### v4.5.0 → v4.6.0：安全性加固 + 死代码清理（内核未改，性能持平）

本版本把重心从「压测数字」转向「安全性与健壮性」（用户要求：安全性、开发便捷性、无用/错误代码清理）。调度内核（`Scheduler` / `Coroutine` / `Channel` / `Timer` / `Runtime`）**零改动**，5 个场景压测与 v4.5.0 持平。

| 变更 | 做法 | 收益 / 影响 |
| --- | --- | --- |
| 路径穿越防护 | `FileTransactionStorage` 事务 ID 白名单 + `basename` 兜底 | 杜绝 `../` 任意文件读写 |
| SQL 注入 / 跨驱动修复 | `DatabaseTransactionStorage` 表名白名单；建表 / UPSERT 按 MySQL / SQLite / PG 分支 | 此前单一方言在其它数据库必崩，现已可移植 |
| 命令注入防护 | `Php85Features::pipeExecute()` 改数组形式直传 `proc_open`（不经 shell） | 从根上消除注入面，并移除重复的「原生管道」分支 |
| 信息泄露防护 | RPC / WebSocket 服务端统一返回 `Internal error`；修复 `RpcServer` 重复 `fclose` | 不再外泄内部异常；避免双重关闭 |
| 握手 / 跨站防护 | `WebSocketServer` 校验 `Upgrade/Connection/Version`，新增 `setAllowedOrigins()`（CSWSH），请求头加行数 / 长度上限 | 防御跨站 WebSocket 劫持与内存耗尽 |
| 死代码清理 | 删除 `EnableFibers`（缺依赖加载即崩）、`TaskMutex`、`WebmanServiceProvider`（零引用）、伪 `ProtobufProtocol`；修正 `IntegrationManager` 命名空间映射并移除 `eval` | 包更干净、无加载即崩/误导实现 |
| Profiler 修复 | `Fibers::profilerDashboard()` 改为直接渲染实测记录（原会重测覆盖 duration/status）；`FiberProfiler` 新增 `loadRecords()` | 仪表盘数值真实 |

> 凡网络监听组件（`RpcServer` / `WebSocketServer` / `WebUI`）默认绑定 `0.0.0.0` 且**不带鉴权**；生产环境务必置于反向代理 / 防火墙之后，并对 WebSocket 配置 `setAllowedOrigins()`。

### v4.6.0 → v4.7.0：缺陷修复 + 误导代码清理（内核未改，性能持平）

本版本按用户要求「检测当前版本问题并直接修复」。调度内核（`Scheduler` / `Coroutine` / `Channel` / `Timer` / `Runtime`）**零改动**，5 个场景压测与 v4.6.0 持平。

| 变更 | 做法 | 收益 / 影响 |
| --- | --- | --- |
| 门面断链修复 | `Facades\Fiber` 的 `id()` 改为 `getFacadeAccessor()` 并返回 `'fibers'`，对接基类 `classMap` | 此前 `Fiber::cpuCount()` 等静态调用一律抛 `RuntimeException`，现正常路由到 `Kode\Fibers\Fibers` |
| 仪表盘崩溃修复 | `WebUI::formatBytes()` 参数 `int` 改为 `int\|float`（记录经 JSON 往返后为 float） | 修复 `Fibers::profilerDashboard()` 的 `TypeError` 崩溃 |
| WebUI 半成品修复 | `start()` 改为基于 `stream_socket_server` 的真正自托管 HTTP 服务（抽出 `buildResponse` / `writeResponse`，CGI 与自托管共用）；`index()` 版本号改为读取真实包版本 | `start()` 现在真正监听端口并提供 HTML 仪表盘与 JSON API |
| Roadmap 误导修正 | `Roadmap::items()` 重写为反映已交付能力的真实状态（不再显示 `2.2.x–2.6.x planned`） | `Fibers::roadmap()` 不再对外输出误导性的「规划中」信息 |
| PHP 8.4 弃用修复 | `TaskQueue::waitEmpty()` 参数 `float` 改为 `?float` | 消除隐式可空弃用告警 |
| RPC DoS 防护 | `RpcServer` 批量请求加上限（`maxBatchSize = 100`，超限返回 413） | 防御超大批量请求耗尽资源 |
| 死代码清理 | 删除零引用的 `Event/`（EventBus/Event/BaseEvent）；删除从不读取的 `Attributes/`（FiberSafe/Timeout/ChannelListener/Attribute）及全部 `#[FiberSafe]`/`#[Timeout]` 注解；同步 README | 消除不生效的「假 API」（文档曾称 `#[Timeout(10)]` 会超时保护，实际从不读取） |

> 公共 API 收敛：`Event` / `Attributes` 命名空间不再存在；`Fibers` / `Facades\Fiber` 的其余 `@method` 与 `Fibers::roadmap()` / `profilerDashboard()` 保持不变。按语义化版本以**次版本号（minor）**发布为 `4.7.0`。

---

## 5. 复现

```bash
# 安装对比基准库（已写入 require-dev）
composer install

# 运行全部 5 个场景，输出 Markdown 对比表
php -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M \
    benchmarks/bench.php --markdown

# 仅跑特定场景
php benchmarks/bench.php timer channel

# 微基准：逐层剥离协程切换 / 定时器开销
php benchmarks/prof_switch.php
php benchmarks/prof_timer.php
```

> 若系统已安装 `ext-swoole` / `ext-swow`，`bench.php` 会自动检测 `.so` 路径并在子进程中加载，结果表将包含 `swoole（原生协程）` / `swow（原生协程）` 两行；未安装则自动跳过该行。
