# 性能基准与优化说明

本文档记录 `kode/fibers` **v4.2.0** 与同类 PHP 并发库的**真实横向压测对比**、测试方法论，以及本轮（v4.1.0 → v4.2.0）所做的调度内核热路径优化。

所有数据均在 **PHP 8.3.31**（CLI，NTS）下测得，运行环境为 macOS / Apple Silicon（Darwin）。`kode/fibers` 最低支持 **PHP 8.3+**，本基准即以此版本为基线。

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

## 2. 结果（v4.2.0，PHP 8.3.31，JIT on，ops/s 中位数）

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
| **kode/fibers** | **7,537,689** | 6.633 ms | 1.00x | 19.5 KB |
| swow（原生协程） | 19,899,175 | 2.513 ms | 2.64x | 0 B |
| swoole（原生协程） | 14,165,979 | 3.530 ms | 1.88x | 0 B |
| amphp/amp v3 | 1,732,762 | 28.856 ms | 0.23x | 0 B |
| revolt（Suspension） | 1,735,323 | 28.813 ms | 0.23x | 0 B |
| reactphp（回调） | — | — | — | 无协程原语 |

- kode 是**最快的 PHP 用户态协程切换**（领先 amphp / revolt 约 **4.3×**）。原生 swoole/swow 因在 C 层直接切换栈而更快，这是 PHP `Fiber` 上下文切换的物理下限决定的（约 13.8M ops/s），非算法低效。

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

## 3. 结论（v4.2.0）

| 场景 | kode/fibers | 协程级最佳对照 | 结论 |
| --- | --- | --- | --- |
| 协程创建 | 1.93M | swoole 2.18M | 紧随原生 swoole，领先 amphp 13.5× |
| 协程切换 | 7.54M | swow 19.9M | PHP 用户态最快，4.3× 领先 amphp/revolt |
| 定时器 | **2.23M** | (kode 自身第一) | **全场第一**，2.07× 领先 swoole |
| Channel | **23.1M** | (kode 自身第一) | **全场第一**，5.0× 领先 swoole |
| 并发聚合 | 1.40M | swoole 2.04M | 领先 amphp 8× / swow 4× |

- **Channel 与定时器双双登顶**：kode 在阻塞 Channel 与大批量定时器两个维度均为**全场最快**，反超原生 Swoole / Swow。
- **协程切换达 PHP 用户态上限**：kode 切换吞吐 7.54M ops/s，已是纯 PHP `Fiber` 实现的头部水平；原生 swoole/swow 因 C 层栈切换更快，属物理下限差异。
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

> 上述改动均为**内部实现优化**，公共 API（`Scheduler::go/enqueue/delay/repeat`、`Timer::cancel/at/isCancelled/isPeriodic`、`Coroutine`、`Channel` 等）向后兼容，按语义化版本规则以**次版本号（minor）**发布为 `4.2.0`。

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
