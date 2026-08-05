# 性能基准与优化说明

本文档记录 `kode/fibers` **v4.1.0** 与同类 PHP 并发库的**真实横向压测对比**、测试方法论，以及本轮（v4.0.0 → v4.1.0）所做的调度内核优化。

所有数据均在 **PHP 8.3.x**（CLI，NTS）下测得，运行环境为 macOS / Apple Silicon。`kode/fibers` 最低支持 **PHP 8.3+**，本基准即以此版本为基线。

---

## 1. 方法论

为保证可比性与可复现：

- **计时**：使用 `hrtime(true)` 单调时钟，仅统计目标操作本身，排除启动/编译开销。
- **稳定性**：每个场景先 **预热 2 轮**，再 **实测 5 轮取中位数**，避免 GC、JIT、CPU 频率波动造成的离群值。
- **内存**：在场景前后各采样 `memory_get_usage()`，记录净增量（B），用于暴露内存泄漏。
- **对比基准**：安装真实同类库作为 `require-dev`，在同一进程内、同一 harness 下对比，不依赖外部脚本或人工估算：

  | 库 | 版本 | 维度 |
  | --- | --- | --- |
  | `amphp/amp` | ^3.0 | 协程 / Promise 组合 |
  | `amphp/pipeline` | ^1.2 | 背压 Channel |
  | `revolt/event-loop` | ^1.0 | 纯回调事件循环 |
  | `react/event-loop` | ^1.5 | 纯回调事件循环 |
  | `react/promise` | ^3.2 | Promise 组合 |

- **公平性说明**：`revolt` / `reactphp` 的「回调」维度为**纯事件循环**（无协程语义、无 `Fiber` 切换），与 `kode/fibers` 的协程级实现**不构成直接等价对比**，仅作参考上限。`amphp/amp` 同为协程/Promise 模型，是协程级最接近的对照。

压测入口：`php benchmarks/bench.php [--markdown] [场景名...]`（`composer bench`）。

---

## 2. 结果（v4.1.0，PHP 8.3.x，ops/s 中位数）

> 相对倍数以 `kode/fibers = 1.00x` 为基准；`—` 表示语义不等价或无对应原语。

### 2.1 协程创建与完成（20,000 个空任务）

| 实现 | ops/s | 中位耗时 | 相对 | 内存增量 |
| --- | ---: | ---: | ---: | ---: |
| **kode/fibers** | **215,949** | 92.6 ms | 1.00x | 0 B |
| amphp/amp v3 | 119,886 | 166.8 ms | 0.56x | 0 B |
| revolt（回调） | 851,870 | 23.5 ms | 3.94x | 0 B |
| reactphp（回调） | 3,710,919 | 5.4 ms | 17.18x | 0 B |

### 2.2 协程切换（单协程让出 50,000 次）

| 实现 | ops/s | 中位耗时 | 相对 | 内存增量 |
| --- | ---: | ---: | ---: | ---: |
| **kode/fibers** | **1,694,300** | 29.5 ms | 1.00x | 0 B |
| amphp/amp v3 | 1,138,140 | 43.9 ms | 0.67x | 0 B |
| revolt（Suspension） | 1,133,082 | 44.1 ms | 0.67x | 0 B |
| reactphp（回调） | — | — | — | 无协程原语 |

### 2.3 定时器调度（20,000 个 0 延迟定时器）

| 实现 | ops/s | 中位耗时 | 相对 | 内存增量 |
| --- | ---: | ---: | ---: | ---: |
| **kode/fibers** | **397,889** | 50.3 ms | 1.00x | 0 B |
| revolt | 536,829 | 37.3 ms | 1.35x | 0 B |
| reactphp | 249,999 | 80.0 ms | 0.63x | 0 B |

### 2.4 Channel 吞吐（50,000 条消息，缓冲 1024）

| 实现 | ops/s | 中位耗时 | 相对 | 内存增量 |
| --- | ---: | ---: | ---: | ---: |
| **kode/fibers** | **1,686,848** | 29.6 ms | 1.00x | 0 B |
| amphp/pipeline | 1,116,432 | 44.8 ms | 0.66x | 0 B |
| reactphp | — | — | — | 无阻塞 Channel |

### 2.5 并发聚合（10,000 个任务收集结果）

| 实现 | ops/s | 中位耗时 | 相对 | 内存增量 |
| --- | ---: | ---: | ---: | ---: |
| **kode/fibers** | **180,681** | 55.3 ms | 1.00x | 0 B |
| amphp/amp v3 | 132,935 | 75.2 ms | 0.74x | 0 B |
| reactphp | — | — | — | 需 Promise 组合，语义不等价 |

---

## 3. 结论

- **协程级维度全面领先**：协程创建 **1.80x**、协程切换 **1.49x**、Channel **1.51x**、并发聚合 **1.36x**，对比最接近的协程/Promise 实现 `amphp/amp`。
- **零内存泄漏**：全部场景内存增量 **0 B**（v4.0.0 曾在 spawn / 聚合场景存在 `WeakMap` 残留，本轮已修复）。
- **定时器**：领先 `reactphp` **1.59x**；与纯回调事件循环 `revolt` 差距约 **1.35x** —— 该差距来自协程调度器固有开销（每定时器需 `Fiber`/`Coroutine` 登记与回调绑定），属架构差异而非低效实现，且已通过分桶堆 + 即时触发把开销压到接近下限。

---

## 4. v4.0.0 → v4.1.0 调度内核优化清单

| 优化项 | 做法 | 收益 |
| --- | --- | --- |
| 就绪队列 | `SplQueue`（每次入队分配链表节点）→ **原生 `array` + `head`/`tail` 游标** | 消除入队分配，协程创建/切换显著提速 |
| 就绪派发 | 多态 `instanceof` 直派（`Fiber`/`Suspension`/`Coroutine`/`Timer`/`Closure`），免闭包包装 | 减少热路径闭包分配与调用层 |
| 定时器堆 | 二叉最小堆 → **按到期时刻分桶**（同刻 FIFO 桶，O(1) 批量进出） | 同刻定时器从逐元素上浮降为桶内 O(1) |
| 定时器到期 | 到期回调**直接触发**，不再绕就绪队列 | 削减一轮队列周转，驱动阶段开销下降 |
| 定时器句柄 | 移除每定时器 `onCancel` 取消闭包（改为直接引用所属堆 `markStale()`） | 零额外闭包分配 |
| 内存泄漏 | `Scheduler::onCoroutineFinished` 显式 `unset($this->owned[$fiber])` | 修复 `WeakMap` 因 `Coroutine↔Fiber` 强引用循环导致的失效，内存归零 |
| 取消路径 | `Suspension::resume()/throw()` 直接 `$scheduler->enqueue($this)` | 省去临时闭包 |

> 上述改动均为**内部实现优化**，公共 API（`Scheduler::go/enqueue/delay/repeat`、`Timer::cancel/at/isCancelled/isPeriodic`、`Coroutine` 等）向后兼容，按语义化版本规则以**次版本号（minor）**发布为 `4.1.0`。

---

## 5. 复现

```bash
# 安装对比基准库（已写入 require-dev）
composer install

# 运行全部 5 个场景，输出 Markdown 对比表
php benchmarks/bench.php --markdown

# 仅跑特定场景
php benchmarks/bench.php timer channel
```
