<?php

declare(strict_types=1);

namespace Nova\Fibers\Scheduler;

use Nova\Fibers\Context\Context;
use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Context\ContextManager;

/**
 * 本地调度器实现
 *
 * 作为分布式调度器的基础实现
 */
class LocalScheduler implements DistributedSchedulerInterface
{
    /**
     * @var FiberPool Fiber池
     */
    private FiberPool $fiberPool;

    /**
     * @var array<string, array{
 *     task: callable,
 *     context: Context|null,
 *     status: string,
 *     result: mixed,
 *     fiber: \Fiber|null
 * }> 任务存储
     */
    private array $tasks = [];

    /**
     * 构造函数
     *
     * @param array $poolOptions Fiber池选项
     */
    public function __construct(array $poolOptions = [])
    {
        $this->fiberPool = new FiberPool($poolOptions);
    }

    /**
     * {@inheritDoc}
     */
    public function submit(callable $task, ?Context $context = null, array $options = []): string
    {
        $taskId = uniqid('task_', true);

        $this->tasks[$taskId] = [
            'task' => $task,
            'context' => $context,
            'status' => 'pending',
            'result' => null,
            'fiber' => null
        ];

        return $taskId;
    }

    /**
     * {@inheritDoc}
     */
    public function getResult(string $taskId, ?float $timeout = null): mixed
    {
        if (!isset($this->tasks[$taskId])) {
            throw new \RuntimeException("Task {$taskId} not found");
        }

        // 如果任务已完成，直接返回结果
        if ($this->tasks[$taskId]['status'] === 'completed') {
            return $this->tasks[$taskId]['result'];
        }

        // 如果任务失败，抛出异常
        if ($this->tasks[$taskId]['status'] === 'failed') {
            throw new \RuntimeException("Task {$taskId} failed");
        }

        // 启动任务如果还未启动
        if ($this->tasks[$taskId]['status'] === 'pending') {
            $this->executeTask($taskId);
        }

        // 等待任务完成
        $startTime = microtime(true);
        while (
            $this->tasks[$taskId]['status'] !== 'completed' &&
               $this->tasks[$taskId]['status'] !== 'failed'
        ) {
            // 检查超时
            if ($timeout !== null && (microtime(true) - $startTime) > $timeout) {
                throw new \RuntimeException("Task {$taskId} timed out");
            }

            // 暂停当前纤程，允许其他纤程运行
            \Fiber::suspend();
        }

        if ($this->tasks[$taskId]['status'] === 'failed') {
            throw new \RuntimeException("Task {$taskId} failed");
        }

        return $this->tasks[$taskId]['result'];
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(string $taskId): bool
    {
        if (!isset($this->tasks[$taskId])) {
            return false;
        }

        // 无法真正取消正在运行的纤程，只能标记为已取消
        $this->tasks[$taskId]['status'] = 'cancelled';

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(string $taskId): string
    {
        if (!isset($this->tasks[$taskId])) {
            return 'unknown';
        }

        return $this->tasks[$taskId]['status'];
    }

    /**
     * {@inheritDoc}
     */
    public function getClusterInfo(): array
    {
        return [
            'nodes' => [
                [
                    'id' => 'local',
                    'address' => '127.0.0.1',
                    'status' => 'online',
                    'tasks' => count($this->tasks)
                ]
            ],
            'total_tasks' => count($this->tasks)
        ];
    }

    /**
     * 执行任务
     *
     * @param string $taskId 任务ID
     * @return void
     */
    private function executeTask(string $taskId): void
    {
        if (!isset($this->tasks[$taskId]) || $this->tasks[$taskId]['status'] !== 'pending') {
            return;
        }

        $task = $this->tasks[$taskId]['task'];
        $context = $this->tasks[$taskId]['context'];

        $this->tasks[$taskId]['status'] = 'running';

        // 设置上下文
        if ($context !== null) {
            ContextManager::setCurrentContext($context);
        }

        try {
            // 使用Fiber池执行任务
            $results = $this->fiberPool->concurrent([$task]);
            $this->tasks[$taskId]['result'] = $results[0];
            $this->tasks[$taskId]['status'] = 'completed';
        } catch (\Throwable $e) {
            $this->tasks[$taskId]['result'] = $e;
            $this->tasks[$taskId]['status'] = 'failed';
        }
    }
}
