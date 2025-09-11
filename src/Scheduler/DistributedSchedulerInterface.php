<?php

declare(strict_types=1);

namespace Nova\Fibers\Scheduler;

use Nova\Fibers\Context\Context;

/**
 * 分布式调度器接口
 *
 * 定义分布式Fiber调度器的标准接口
 */
interface DistributedSchedulerInterface
{
    /**
     * 提交任务到分布式调度器
     *
     * @param callable $task 任务回调函数
     * @param Context|null $context 上下文
     * @param array $options 调度选项
     * @return string 任务ID
     */
    public function submit(callable $task, ?Context $context = null, array $options = []): string;

    /**
     * 获取任务结果
     *
     * @param string $taskId 任务ID
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 任务结果
     * @throws \RuntimeException 如果任务执行失败或超时
     */
    public function getResult(string $taskId, ?float $timeout = null): mixed;

    /**
     * 取消任务
     *
     * @param string $taskId 任务ID
     * @return bool 是否成功取消
     */
    public function cancel(string $taskId): bool;

    /**
     * 获取任务状态
     *
     * @param string $taskId 任务ID
     * @return string 任务状态 (pending, running, completed, failed, cancelled)
     */
    public function getStatus(string $taskId): string;

    /**
     * 获取集群节点信息
     *
     * @return array 集群节点信息
     */
    public function getClusterInfo(): array;
}
