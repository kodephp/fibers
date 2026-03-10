# 路线图（Roadmap）

本文档说明 Kode/Fibers 在后续版本中的扩展方向、阶段目标与兼容策略。

## 2.2.x（当前阶段）

- 上下文传递机制：增强 `Fibers::withContext()` 与批处理场景上下文传播
- 负载均衡能力：提供批量任务分片执行接口 `Fibers::batch()`
- PHP 8.5 兼容准备：提供 `Fibers::runtimeFeatures()` 与能力检测
- 开发便捷性：提供 `Fibers::go()`、`fiber_go()`、`fiber_batch()` 等简化入口

## 2.3.x（下一阶段）

- 分布式 Fiber 调度：跨节点任务分配、节点健康检查、重试转移
- 性能监控面板：采集任务耗时、失败率、并发度，并提供可视化面板

## 2.4.x（生态桥接阶段）

- 生态系统集成：对接 Swoole / OpenSwoole / Swow / Workerman 运行时
- ORM 适配层：Fiber-aware 适配 Eloquent、Doctrine 与自定义仓储模式

## 2.5.x（稳定性治理阶段）

- 断路器模式：自动熔断、半开探测、故障恢复
- 任务治理增强：慢任务识别、回压保护、优先级动态调节

## 2.6.x（平台化阶段）

- 热重载支持：不中断服务更新代码与配置
- 可视化管理界面：Fiber 池、队列、任务执行状态的 Web UI

## 持续路线

- 更多框架支持：持续扩展 Laravel / Symfony / Yii3 / ThinkPHP / 原生项目之外的生态
- 开发体验优化：继续增强 helper、Facade、命令行工具与文档可读性
