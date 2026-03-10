# 使用示例

本页示例与 `examples/` 目录中的脚本对应，建议直接运行验证。

## 1. 基础执行

- 文件：`examples/simple_example.php`
- 说明：演示 `Fibers::run()` 与 `Fibers::go()`

## 2. 批处理

- 文件：`examples/fiber_pool_example.php`
- 说明：演示 `batch` 并发分片处理

## 3. 容错批处理

- 推荐新增脚本：`examples/resilient_batch_example.php`
- 说明：演示失败重试、熔断、错误聚合

## 4. 分布式分配

- 推荐新增脚本：`examples/distributed_scheduler_example.php`
- 说明：演示任务在多节点的分配结果

## 运行方式

```bash
php examples/simple_example.php
php examples/fiber_pool_example.php
php examples/resilient_batch_example.php
php examples/distributed_scheduler_example.php
```
