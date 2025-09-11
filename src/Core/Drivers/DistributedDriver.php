<?php

declare(strict_types=1);

namespace Nova\Fibers\Core\Drivers;

use Nova\Fibers\Core\Scheduler;

/**
 * 分布式调度器驱动类
 * 
 * 用于在分布式环境中调度纤程任务
 */
class DistributedDriver
{
    /**
     * 调度器实例
     * 
     * @var Scheduler
     */
    protected Scheduler $scheduler;

    /**
     * 构造函数
     * 
     * @param Scheduler $scheduler 调度器实例
     */
    public function __construct(Scheduler $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    /**
     * 添加远程任务
     * 
     * @param string $taskData 任务数据（序列化后的任务）
     * @return void
     */
    public function addRemoteTask(string $taskData): void
    {
        // 在实际实现中，这里会将任务发送到远程节点
        // 为简化示例，我们直接将任务添加到本地调度器
        
        // 反序列化任务数据
        $task = unserialize($taskData);
        
        // 添加到调度器
        if (is_callable($task)) {
            $this->scheduler->addTask($task);
        }
    }

    /**
     * 获取节点状态
     * 
     * @return array 节点状态信息
     */
    public function getNodeStatus(): array
    {
        // 在实际实现中，这里会收集节点的详细状态信息
        // 包括CPU使用率、内存使用情况、活跃纤程数量等
        return [
            'node_id' => uniqid(),
            'timestamp' => time(),
            'status' => 'active',
            'fiber_count' => 0, // 需要从调度器获取实际数据
        ];
    }
}