<?php

declare(strict_types=1);

namespace Nova\Fibers\Core;

use Fiber;
use RuntimeException;
use Nova\Fibers\Contracts\Runnable;

/**
 * 高性能纤程池实现
 * 
 * 管理一组纤程的创建、执行和回收，支持并发任务处理和资源管理
 */
class FiberPool
{
    /**
     * 纤程池配置
     * 
     * @var array
     */
    protected array $config;

    /**
     * 纤程池中的纤程列表
     * 
     * @var Fiber[]
     */
    protected array $fibers = [];

    /**
     * 空闲纤程队列
     * 
     * @var Fiber[]
     */
    protected array $idleFibers = [];

    /**
     * 构造函数
     * 
     * @param array $config 纤程池配置
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'size' => 64,
            'max_exec_time' => 30,
            'gc_interval' => 100,
            'context' => [],
        ], $config);
    }

    /**
     * 并发执行多个任务
     * 
     * @param callable[] $tasks 任务数组
     * @param float|null $timeout 超时时间（秒）
     * @return array 任务执行结果
     * @throws RuntimeException 如果任务执行超时
     */
    public function concurrent(array $tasks, ?float $timeout = null): array
    {
        $results = [];
        $fibers = [];
        $startTime = microtime(true);

        // 创建纤程并启动任务
        foreach ($tasks as $key => $task) {
            $fiber = new Fiber($task);
            $fiber->start();
            $fibers[$key] = $fiber;
        }

        // 等待所有任务完成
        while (!empty($fibers)) {
            // 检查是否超时
            if ($timeout !== null && (microtime(true) - $startTime) >= $timeout) {
                throw new RuntimeException("Task execution timed out after {$timeout} seconds");
            }
            
            foreach ($fibers as $key => $fiber) {
                if ($fiber->isTerminated()) {
                    $results[$key] = $fiber->getReturn();
                    unset($fibers[$key]);
                } elseif ($fiber->isSuspended()) {
                    $fiber->resume();
                }
            }
            
            // 避免CPU占用过高
            usleep(1000);
        }

        return $results;
    }

    /**
     * 提交一个任务到纤程池执行
     * 
     * @param Runnable $task 可执行任务
     * @return mixed 任务执行结果
     */
    public function submit(Runnable $task)
    {
        // 如果有空闲纤程，复用它
        if (!empty($this->idleFibers)) {
            $fiber = array_pop($this->idleFibers);
        } else {
            // 创建新纤程
            $fiber = new Fiber(function () use ($task) {
                while (true) {
                    $task->run();
                    // 将纤程标记为空闲
                    $this->idleFibers[] = Fiber::getCurrent();
                    // 挂起纤程等待新任务
                    Fiber::suspend();
                }
            });
            $this->fibers[] = $fiber;
        }

        // 启动或恢复纤程执行任务
        if (!$fiber->isStarted()) {
            $fiber->start();
        } else {
            $fiber->resume($task);
        }

        // 等待任务完成并返回结果
        while (!$fiber->isTerminated()) {
            if ($fiber->isSuspended()) {
                break;
            }
            usleep(1000);
        }

        return $task->getResult();
    }

    /**
     * 获取纤程池大小
     * 
     * @return int 纤程池大小
     */
    public function getSize(): int
    {
        return $this->config['size'];
    }

    /**
     * 获取当前活跃纤程数
     * 
     * @return int 活跃纤程数
     */
    public function getActiveCount(): int
    {
        return count($this->fibers) - count($this->idleFibers);
    }

    /**
     * 获取空闲纤程数
     * 
     * @return int 空闲纤程数
     */
    public function getIdleCount(): int
    {
        return count($this->idleFibers);
    }

    /**
     * 清理已终止的纤程
     * 
     * @return void
     */
    public function cleanup(): void
    {
        foreach ($this->fibers as $key => $fiber) {
            if ($fiber->isTerminated()) {
                unset($this->fibers[$key]);
            }
        }
        
        foreach ($this->idleFibers as $key => $fiber) {
            if ($fiber->isTerminated()) {
                unset($this->idleFibers[$key]);
            }
        }
    }
}
