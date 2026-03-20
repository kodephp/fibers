# 路线图（Roadmap）

## 3.1.x 已完成

- ✅ 便捷 API：`go`、`withContext`、`batch`
- ✅ 容错批处理：`resilientBatch`
- ✅ 断路器与负载均衡：`CircuitBreaker`、`RoundRobinBalancer`
- ✅ 分布式分配入口：`scheduleDistributed`、`scheduleDistributedAdvanced`、`remote`、`onNode`
- ✅ 上下文传递机制：使用 `kode/context` 实现，支持跨设备
- ✅ Profiler 数据采集：`FiberProfiler` 性能分析
- ✅ 运行时桥接：`RuntimeBridge` 支持 Swoole/OpenSwoole/Swow/Workerman
- ✅ ORM 适配层：`EloquentAdapter`、`FixturesAdapter`
- ✅ 热重载支持：`HotReloader` 不中断服务更新代码
- ✅ 可视化管理界面：`WebUI` Web UI 管理纤程池和任务
- ✅ 更多框架支持：Lumen、Hyperf、Webman 服务提供者
- ✅ 连接池支持：`ConnectionPool` 支持 PDO、Redis 连接池
- ✅ 协程调试器：`FiberDebugger` 支持断点、日志、状态监控
- ✅ PHP 8.5 特性支持：`Php85Features` 自动适配新特性
- ✅ 自动降级机制：无依赖时使用原生实现，有依赖时使用包功能
- ✅ 依赖优化：完整功能包在 require 中，Composer 自动去重
- ✅ FiberPool 架构优化：修复连接池复用逻辑问题
- ✅ 分布式调度执行层增强：`EnhancedDistributedScheduler` 任务回执与重试转移
- ✅ 多维度断路器：`MultiDimensionalCircuitBreaker` 按异常类型与服务维度
- ✅ 高级负载均衡：`AdvancedLoadBalancer` 最小连接、权重策略
- ✅ IO_uring 支持：`IOuringSupport` 高性能异步 I/O 接口
- ✅ **多框架接入自动化**：`FrameworkDetector` 自动检测框架
- ✅ **原生异步 IO**：`AsyncIO` 统一异步 IO 接口
- ✅ **跨语言 RPC 通信**：`RpcClient` / `RpcServer` 支持 JSON/MessagePack 协议
- ✅ **分布式事务**：`DistributedTransactionManager` 支持 2PC/TCC/Saga 模式

## 3.2.x 计划

- 跨语言 RPC 通信增强（gRPC、WebSocket 支持）
- 分布式事务持久化
- 更多框架支持

## 远期计划

- 跨语言 RPC 通信
- 分布式事务支持
