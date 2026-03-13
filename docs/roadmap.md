# 路线图（Roadmap）

## 2.7.x 已完成

- ✅ 便捷 API：`go`、`withContext`、`batch`
- ✅ 容错批处理：`resilientBatch`
- ✅ 断路器与负载均衡：`CircuitBreaker`、`RoundRobinBalancer`
- ✅ 分布式分配入口：`scheduleDistributed`、`scheduleDistributedAdvanced`、`remote`、`onNode`
- ✅ 上下文传递机制：使用 `kode/context` 实现，支持跨设备
- ✅ Profiler 数据采集：`FiberProfiler` 性能分析
- ✅ 运行时桥接：`RuntimeBridge` 支持 Swoole/OpenSwoole/Swow/Workerman
- ✅ ORM 适配层：`EloquentAdapter`、`FixturesAdapter`
- ✅ 热重载支持：`HotReloader` 不中断服务更新代码
- ✅ 可视化管理界面：`WebUI` Web UI 管理纤程池和任务（合并ProfilerDashboard）
- ✅ 更多框架支持：Lumen、Hyperf、Webman 服务提供者
- ✅ 连接池支持：`ConnectionPool` 支持 PDO、Redis 连接池
- ✅ 协程调试器：`FiberDebugger` 支持断点、日志、状态监控
- ✅ PHP 8.5 特性支持：`Php85Features` 自动适配新特性

## 2.8.x 计划

- 分布式调度执行层增强（任务回执、重试转移）
- 断路器策略扩展（按异常类型与服务维度）
- 负载均衡策略扩展：最小连接、权重策略
- IO_uring 支持（需要 PHP 扩展支持）

## 3.x 远期计划

- 更多框架支持：RoadRunner、Spiral 等
- 多框架接入自动化脚手架
- 原生异步 IO 支持
