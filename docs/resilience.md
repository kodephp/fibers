# 容错与调度

## 断路器（CircuitBreaker）

断路器用于避免持续请求失败端点，核心状态如下：

- `closed`：正常放行
- `open`：达到失败阈值后拒绝请求
- `half_open`：恢复窗口后允许少量探测请求

### 使用方式

`Fibers::resilientBatch()` 内部默认启用断路器，可通过参数调整：

```php
$response = Fibers::resilientBatch($items, $handler, [
    'failure_threshold' => 5,
    'recovery_timeout' => 3.0,
    'half_open_max_calls' => 1,
    'max_retries' => 1,
    'fail_fast' => false,
]);
```

### 返回结构

- `results`：成功结果
- `errors`：失败项异常集合
- `skipped`：被熔断跳过的任务
- `metrics`：总量、成功数、失败数、跳过数
- `breaker`：当前断路器状态与计数

## 负载均衡（RoundRobin）

`RoundRobinBalancer` 提供两类能力：

- `nextNode()`：依次轮询节点
- `distribute()`：按轮询把任务分配到 worker 桶

该策略用于保持任务分布均匀，避免单 worker 过载。
