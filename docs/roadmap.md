# 路线图（Roadmap）

## v3.3.x 当前版本 ✅

### 核心功能
- ✅ 便捷 API：`go`、`withContext`、`batch`
- ✅ 容错批处理：`resilientBatch`
- ✅ 断路器与负载均衡：`CircuitBreaker`、`RoundRobinBalancer`
- ✅ 分布式分配入口：`scheduleDistributed`、`scheduleDistributedAdvanced`

### 上下文与调度
- ✅ 上下文传递机制：使用 `kode/context` 实现，支持跨设备
- ✅ 增强型分布式调度器：`EnhancedDistributedScheduler` 任务回执与重试转移
- ✅ 高级负载均衡：`AdvancedLoadBalancer` 最小连接、权重策略

### 运行时集成
- ✅ Profiler 数据采集：`FiberProfiler` 性能分析
- ✅ 运行时桥接：`RuntimeBridge` 支持 Swoole/OpenSwoole/Swow/Workerman
- ✅ 热重载支持：`HotReloader` 不中断服务更新代码
- ✅ 多框架支持：Lumen、Hyperf、Webman 服务提供者

### 连接池与 ORM
- ✅ 连接池支持：`ConnectionPool` 支持 PDO、Redis 连接池
- ✅ ORM 适配层：`EloquentAdapter`、`FixturesAdapter`

### 协程支持
- ✅ 协程调试器：`FiberDebugger` 支持断点、日志、状态监控
- ✅ PHP 8.5 特性支持：`Php85Features` 自动适配新特性
- ✅ IO_uring 支持：`IOuringSupport` 高性能异步 I/O 接口

### RPC 通信
- ✅ JSON-RPC：`RpcClient` / `RpcServer`
- ✅ MessagePack-RPC：高效二进制格式
- ✅ gRPC 支持：`GrpcProtocol` / `GrpcClient`
- ✅ WebSocket RPC：`WebSocketServer` / `WebSocketClient`

### 分布式事务
- ✅ 2PC 两阶段提交模式
- ✅ TCC 补偿型事务模式
- ✅ Saga 链式补偿模式
- ✅ 事务持久化：`PersistentTransactionManager` 文件/数据库存储

### 框架集成
- ✅ 自动降级机制：无依赖时使用原生实现，有依赖时使用包功能
- ✅ 多框架接入自动化：`FrameworkDetector` 自动检测框架
- ✅ 原生异步 IO：`AsyncIO` 统一异步 IO 接口

### 架构优化
- ✅ 依赖优化：完整功能包在 require 中，Composer 自动去重
- ✅ FiberPool 连接池架构优化：修复 Fiber 复用逻辑问题

## v3.4.x 计划

### RPC 通信增强
- [ ] gRPC 服务端支持
- [ ] WebSocket TLS/SSL 支持
- [ ] RPC 连接池
- [ ] RPC 负载均衡

### 事务增强
- [ ] 事务追踪系统
- [ ] Saga 编排器可视化
- [ ] 分布式锁集成

### 工具
- [ ] 性能基准测试工具
- [ ] 事务监控面板
- [ ] CLI 调试工具

## v3.5.x 计划

### 框架支持
- [ ] RoadRunner 支持
- [ ] Spiral 支持
- [ ] 多框架接入自动化脚手架

### 高级特性
- [ ] 原生异步 IO 增强
- [ ] 流式处理支持
- [ ] 响应式编程接口

## 远期规划

### 分布式
- [ ] 分布式锁管理器
- [ ] 服务网格集成
- [ ] 多机房容灾

### 性能
- [ ] SIMD 指令优化
- [ ] 内存池优化
- [ ] 零拷贝网络 IO
