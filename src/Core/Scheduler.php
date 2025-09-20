<?php

declare(strict_types=1);

namespace Nova\Fibers\Core;

use Fiber;
use Nova\Fibers\Channel\Channel;

/**
 * 调度器类
 * 
 * 管理和调度纤程任务执行，基于事件循环实现
 */
class Scheduler
{
    /**
     * 任务队列通道
     * 
     * @var Channel
     */
    protected Channel $taskQueue;

    /**
     * 是否正在运行
     * 
     * @var bool
     */
    protected bool $running = false;

    /**
     * 事件循环实例
     * 
     * @var EventLoop
     */
    protected EventLoop $eventLoop;

    /**
     * 活跃纤程列表
     * 
     * @var array<string, Fiber>
     */
    protected array $fibers = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->taskQueue = new Channel('scheduler_queue', 1000);
        $this->eventLoop = EventLoop::getInstance();
    }

    /**
     * 添加任务到调度器
     * 
     * @param callable $task 要执行的任务
     * @return string 任务ID
     */
    public function addTask(callable $task): string
    {
        $taskId = uniqid('task_', true);
        $this->taskQueue->push(['id' => $taskId, 'task' => $task]);
        return $taskId;
    }

    /**
     * 运行调度器
     * 
     * @return void
     */
    public function run(): void
    {
        $this->running = true;
        
        // 使用事件循环处理任务队列
        $this->eventLoop->repeat(0.001, function() {
            if (!$this->running) {
                $this->eventLoop->stop();
                return;
            }
            
            // 尝试从任务队列获取任务（非阻塞）
            $taskData = $this->taskQueue->pop(0);
            
            if ($taskData !== null) {
                // 创建并启动纤程执行任务
                $fiber = new Fiber($taskData['task']);
                $this->fibers[$taskData['id']] = $fiber;
                $fiber->start();
            }
            
            // 检查活跃纤程状态
            $this->checkFibers();
        });
        
        // 运行事件循环
        $this->eventLoop->run();
    }

    /**
     * 检查纤程状态
     * 
     * @return void
     */
    private function checkFibers(): void
    {
        foreach ($this->fibers as $taskId => $fiber) {
            if ($fiber->isTerminated()) {
                // 移除已完成的纤程
                unset($this->fibers[$taskId]);
            } elseif ($fiber->isSuspended()) {
                // 恢复挂起的纤程
                try {
                    $fiber->resume();
                } catch (\Throwable $e) {
                    // 记录错误并移除纤程
                    error_log("Error resuming fiber $taskId: " . $e->getMessage());
                    unset($this->fibers[$taskId]);
                }
            }
        }
    }

    /**
     * 停止调度器
     * 
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * 获取任务队列
     * 
     * @return Channel 任务队列通道
     */
    public function getTaskQueue(): Channel
    {
        return $this->taskQueue;
    }
    
    /**
     * 获取活跃纤程数量
     * 
     * @return int 活跃纤程数量
     */
    public function getActiveFiberCount(): int
    {
        return count($this->fibers);
    }
}