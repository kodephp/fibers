# API 参考

## Fibers

### 执行与上下文

- `run(callable $task, ?float $timeout = null): mixed`
- `go(callable $task, ?float $timeout = null): mixed`
- `resilientRun(callable $task, array $options = []): mixed`
- `withContext(array $context, callable $task, ?float $timeout = null): mixed`
- `concurrentWithContext(array $context, array $tasks, ?float $timeout = null): array`
- `withTimeout(callable $task, float $timeout): mixed`

### 并发与批处理

- `concurrent(array $tasks, ?float $timeout = null): array`
- `batch(array $items, callable $handler, ?int $concurrency = null, ?float $timeout = null): array`
- `resilientBatch(array $items, callable $handler, array $options = []): array`
- `parallel(array $tasks, ?callable $callback = null): mixed`
- `waitAll(array $tasks): void`

### 运行时与诊断

- `runtimeFeatures(): array`
- `diagnose(): array`
- `roadmap(): array`

### 调度相关

- `scheduleDistributed(array $tasks, array $nodes = []): array`
- `scheduleDistributedAdvanced(array $tasks, array $nodes = [], array $options = []): array`
- `scheduleDistributedRemote(array $tasks, array $nodes = [], ?NodeTransportInterface $transport = null): array`
- `runtimeBridgeInfo(): array`
- `runOnBridge(callable $task, ?string $preferred = null): mixed`
- `profile(callable $task, string $name = 'task'): array`
- `profilerDashboard(array $records): string`
- `eloquent(object $connection): EloquentAdapter`
- `fixtures(array $fixtures = []): FixturesAdapter`

## helper 函数

- `fiber_go()`
- `fiber_with_context()`
- `fiber_batch()`
- `fiber_resilient_batch()`
- `fiber_resilient_run()`
- `fiber_concurrent_with_context()`
- `fiber_schedule_distributed()`
- `fiber_schedule_distributed_advanced()`
- `fiber_schedule_distributed_remote()`
- `fiber_runtime_bridge_info()`
- `fiber_run_on_bridge()`
- `fiber_profile()`
- `fiber_runtime_features()`
- `fiber_roadmap()`

## resilientBatch 参数

`resilientBatch` 支持以下配置键：

- `concurrency`：并发 worker 数
- `timeout`：单批次超时
- `fail_fast`：是否首错即抛出
- `max_retries`：单项最大重试次数
- `failure_threshold`：熔断失败阈值
- `recovery_timeout`：熔断恢复时间（秒）
- `half_open_max_calls`：半开状态最大探测次数
