<?php

declare(strict_types=1);

namespace Nova\Fibers\Core;

use Fiber;
use Nova\Fibers\Channel\Channel;

/**
 * 调度器类
 * 
 * 管理和调度纤程任务执行
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
     * 构造函数
     */
    public function __construct()
    {
        $this->taskQueue = new Channel('scheduler_queue', 1000);
    }

    /**
     * 添加任务到调度器
     * 
     * @param callable $task 要执行的任务
     * @return void
     */
    public function addTask(callable $task): void
    {
        $this->taskQueue->push($task);
    }

    /**
     * 运行调度器
     * 
     * @return void
     */
    public function run(): void
    {
        $this->running = true;
        
        while ($this->running) {
            // 从任务队列获取任务
            $task = $this->taskQueue->pop(0.1); // 100ms超时
            
            if ($task !== null) {
                // 创建并启动纤程执行任务
                $fiber = new Fiber($task);
                $fiber->start();
            }
            
            // 避免CPU占用过高
            usleep(1000);
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
}