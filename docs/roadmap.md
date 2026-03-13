# 路线图（Roadmap）

## 2.5.x 已完成

- ✅ 便捷 API：`go`、`withContext`、`batch`
- ✅ 容错批处理：`resilientBatch`
- ✅ 断路器与负载均衡：`CircuitBreaker`、`RoundRobinBalancer`
- ✅ 分布式分配入口：`scheduleDistributed`、`scheduleDistributedAdvanced`
- ✅ 上下文传递机制：使用 `kode/context` 实现，支持跨设备
- ✅ Profiler 数据采集：`FiberProfiler` 性能分析
- ✅ 运行时桥接：`RuntimeBridge` 支持 Swoole/OpenSwoole/Swow/Workerman
- ✅ ORM 适配层：`EloquentAdapter`、`FixturesAdapter`

## 2.6.x 计划

- 分布式调度执行层（任务回执、重试转移）
- Profiler 可视化面板 Web UI
- 断路器策略扩展（按异常类型与服务维度）
- 负载均衡策略扩展：最小连接、权重策略

## 3.x 远期计划

- 热重载支持
- Web 管理界面
- 多框架接入自动化脚手架
- 更多框架支持
