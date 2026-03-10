# 分布式任务分配

## 目标

`DistributedScheduler` 用于把任务分配到多个节点，为后续“跨机器执行”打基础。

当前版本聚焦于分配层，不直接负责远程传输和执行回执。

## 基本用法

```php
use Kode\Fibers\Fibers;

$tasks = [
    't1' => ['job' => 'sync-user'],
    't2' => ['job' => 'sync-order'],
    't3' => ['job' => 'sync-invoice'],
];

$nodes = [
    'node-a' => ['healthy' => true],
    'node-b' => ['healthy' => true],
];

$result = Fibers::scheduleDistributed($tasks, $nodes);
print_r($result['assignments']);
```

## 返回结构

- `assignments`：按 `nodeId => [taskId => payload]` 分组
- `unassigned`：无健康节点时未分配任务

## 注意事项

1. 该调度器不持久化状态，进程重启后需重新注册节点。
2. 建议业务侧在执行层补充幂等与重试。
3. 节点健康判定建议接入外部探针系统（如心跳服务）。
