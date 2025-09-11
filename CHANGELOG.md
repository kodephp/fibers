# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Fiber Context传递机制
- 分布式调度器接口和本地调度器实现
- Fiber Profiler性能分析器和Web可视化面板
- Fiber-aware ORM（Eloquent/Fixtures适配器）
- CLI命令用于初始化和性能分析
- 全面的使用示例和文档
- 单元测试覆盖所有核心功能
- 完整的纤程池实现，支持并发执行和超时控制
- Channel通信机制，支持纤程间数据传递
- EventBus事件发布/订阅系统
- 环境诊断工具，检测运行环境是否适合纤程运行
- CPU核心数检测功能
- 完整的单元测试套件
- 详细的使用示例和文档

### Changed
- 改进了超时控制机制，修复了超时检查逻辑
- 优化了FiberPool的性能和资源管理
- 增强了错误处理和异常捕获
- 增强了README.md文档，添加了详细的使用说明

### Fixed
- 修复了EventBusTest中的监听器残留问题
- 修复了测试中的"Risky"提示，为所有测试方法添加了@covers注解
- 修复了PHP 8.4析构函数中纤程切换的兼容性问题

## [0.1.0] - 2024-05-20

### Added
- Initial release
- Basic FiberPool implementation
- Simple Channel communication
- Basic EventBus functionality
- Environment support checking