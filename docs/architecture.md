# 架构设计

## 核心组件

- `Fibers`：统一门面与静态入口，负责高层 API 编排
- `FiberPool`：纤程执行池，负责任务执行与重试
- `TaskRunner`：任务执行策略封装（超时、并发、重试）
- `Channel`：纤程间通信通道
- `CircuitBreaker`：容错熔断器
- `RoundRobinBalancer`：任务均衡分配策略
- `DistributedScheduler`：跨节点任务分配器（分配层）

## 设计原则

1. 先可用再增强：优先保证 API 稳定和运行正确。
2. 容错可配置：重试、熔断、失败策略由调用方选择。
3. 扩展可插拔：调度、负载策略通过独立类隔离。
4. 文档与测试同步：每次新增 API 必须同步示例与测试。

## 数据流（批处理）

1. 调用 `Fibers::resilientBatch()`
2. `RoundRobinBalancer` 将任务分桶
3. 每个任务执行前经 `CircuitBreaker::allowRequest()` 判定
4. 任务执行成功 `recordSuccess()`，失败 `recordFailure()`
5. 返回 `results/errors/skipped/metrics/breaker`

## PHP 8.5 兼容策略

- 保持对 PHP 8.1+ 的兼容实现
- 通过 `Fibers::runtimeFeatures()` 暴露运行时特征，便于在业务层做分支适配
- 新增便捷方法 `go/withContext/batch/resilientBatch`，降低调用复杂度
