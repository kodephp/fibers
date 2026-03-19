<?php

declare(strict_types=1);

namespace Kode\Fibers\Core;

use Kode\Fibers\Support\CpuInfo;
use Kode\Fibers\Exceptions\FiberException;
use Kode\Fibers\Contracts\Runnable;
use Kode\Fibers\Task\Task;
use Kode\Context\Context;
use Kode\Fibers\Fibers;
use Fiber;
use RuntimeException;

/**
 * Fiber池 - 管理和复用Fiber实例
 */
class FiberPool
{
    /**
     * 池配置
     *
     * @var array
     */
    protected array $config;

    /**
     * 可用的Fiber实例队列
     *
     * @var array
     */
    protected array $available = [];

    /**
     * 正在运行的Fiber实例
     *
     * @var array
     */
    protected array $running = [];

    /**
     * 池大小
     *
     * @var int
     */
    protected int $size;

    /**
     * 执行任务计数器
     *
     * @var int
     */
    protected int $taskCounter = 0;

    /**
     * 统计信息
     *
     * @var array
     */
    protected array $stats = [
        'success' => 0,
        'failed' => 0,
        'retries' => 0,
        'timeouts' => 0,
        'total_execution_time' => 0
    ];

    /**
     * 构造函数
     *
     * @param array $config 池配置
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'size' => CpuInfo::get() * 4,
            'max_exec_time' => 30,
            'gc_interval' => 100,
            'max_retries' => 3,
            'retry_delay' => 1,
            'onCreate' => null,
            'onDestroy' => null,
            'onTaskStart' => null,
            'onTaskComplete' => null,
            'onTaskFail' => null,
            'context' => [],
            'name' => 'default',
            'strict_mode' => true
        ], $config);

        $this->size = $this->config['size'];
    }

    /**
     * 运行单个任务
     *
     * @param callable|Runnable|string $task 任务回调、Runnable对象或类名
     * @param float|null $timeout 超时时间（秒）
     * @param array $args 额外参数
     * @return mixed
     * @throws FiberException|RuntimeException
     */
    public function run(callable|Runnable|string $task, ?float $timeout = null, array $args = []): mixed
    {
        // 检查任务类型并转换为Runnable对象
        $runnable = $this->prepareTask($task, $args, $timeout);
        
        $maxRetries = $this->config['max_retries'];
        $retryDelay = $this->config['retry_delay'];
        $attempt = 0;
        $startTime = microtime(true);

        while ($attempt <= $maxRetries) {
            $fiber = $this->getFiber();
            
            try {
                // 触发任务开始事件
                if ($this->config['onTaskStart']) {
                    ($this->config['onTaskStart'])($runnable);
                }
                
                $fiber->start($runnable);
                
                // 等待任务完成
                while (!$fiber->isTerminated()) {
                    $fiber->resume();
                }

                $result = $fiber->getReturn();
                
                // 更新统计信息
                $this->stats['success']++;
                $this->stats['total_execution_time'] += microtime(true) - $startTime;
                
                // 触发任务完成事件
                if ($this->config['onTaskComplete']) {
                    ($this->config['onTaskComplete'])($runnable, $result);
                }
                
                return $result;
            } catch (\Throwable $e) {
                // 增加失败计数
                $this->stats['failed']++;
                
                // 触发任务失败事件
                if ($this->config['onTaskFail']) {
                    ($this->config['onTaskFail'])($runnable, $e);
                }
                
                // 如果达到最大重试次数，抛出异常
                if ($attempt >= $maxRetries) {
                    throw new FiberException('Task failed after ' . ($maxRetries + 1) . ' attempts: ' . $e->getMessage(), (int)$e->getCode(), $e);
                }
                
                // 增加重试计数
                $attempt++;
                $this->stats['retries']++;
                
                // 等待重试延迟
                if ($retryDelay > 0) {
                    usleep((int)($retryDelay * 1000000));
                }
            } finally {
                $this->releaseFiber($fiber);
            }
        }
        
        throw new RuntimeException('Task failed after maximum retries');
    }

    /**
     * 并行运行多个任务
     *
     * @param array $tasks 任务数组
     * @param float|null $timeout 总超时时间（秒）
     * @return array
     * @throws FiberException
     */
    public function concurrent(array $tasks, ?float $timeout = null): array
    {
        $results = [];
        $fibers = [];
        $attempts = []; // 跟踪每个任务的尝试次数
        $startTimes = []; // 跟踪每个任务的开始时间
        $totalStartTime = microtime(true);
        
        // 初始化任务和尝试次数
        foreach ($tasks as $key => $task) {
            $attempts[$key] = 0;
            $startTimes[$key] = microtime(true);
            
            try {
                // 准备任务
                $runnable = $this->prepareTask($task, [], null);
                
                // 触发任务开始事件
                if ($this->config['onTaskStart']) {
                    ($this->config['onTaskStart'])($runnable);
                }
                
                $fiber = $this->getFiber();
                $fiber->start($runnable);
                $fibers[$key] = [
                    'fiber' => $fiber,
                    'task' => $runnable
                ];
            } catch (\Throwable $e) {
                $results[$key] = $e;
                $this->stats['failed']++;
            }
        }
        
        // 等待所有任务完成、超时或达到最大重试次数
        while (!empty($fibers)) {
            // 检查总超时
            if ($timeout && (microtime(true) - $totalStartTime) > $timeout) {
                throw new FiberException('Concurrent execution timed out after ' . $timeout . ' seconds');
            }
            
            foreach ($fibers as $key => $item) {
                $fiber = $item['fiber'];
                $runnable = $item['task'];
                
                if ($fiber->isTerminated()) {
                    try {
                        $results[$key] = $fiber->getReturn();
                        $this->stats['success']++;
                        $this->stats['total_execution_time'] += microtime(true) - $startTimes[$key];
                        
                        // 触发任务完成事件
                        if ($this->config['onTaskComplete']) {
                            ($this->config['onTaskComplete'])($runnable, $results[$key]);
                        }
                    } catch (\Throwable $e) {
                        $this->stats['failed']++;
                        
                        // 检查是否可以重试
                        $maxRetries = $this->config['max_retries'];
                        if ($attempts[$key] < $maxRetries) {
                            // 增加重试计数
                            $attempts[$key]++;
                            $this->stats['retries']++;
                            
                            // 等待重试延迟
                            $retryDelay = $this->config['retry_delay'];
                            if ($retryDelay > 0) {
                                usleep((int)($retryDelay * 1000000));
                            }
                            
                            // 重新创建Fiber并启动任务
                            $this->releaseFiber($fiber);
                            $newFiber = $this->getFiber();
                            $newFiber->start($runnable);
                            $fibers[$key]['fiber'] = $newFiber;
                            $startTimes[$key] = microtime(true);
                            continue;
                        }
                        
                        // 达到最大重试次数，记录错误
                        $results[$key] = $e;
                        
                        // 触发任务失败事件
                        if ($this->config['onTaskFail']) {
                            ($this->config['onTaskFail'])($runnable, $e);
                        }
                    } finally {
                        $this->releaseFiber($fiber);
                        unset($fibers[$key]);
                    }
                } else {
                    try {
                        $fiber->resume();
                    } catch (\Throwable $e) {
                        $this->stats['failed']++;
                        
                        // 检查是否可以重试
                        $maxRetries = $this->config['max_retries'];
                        if ($attempts[$key] < $maxRetries) {
                            // 增加重试计数
                            $attempts[$key]++;
                            $this->stats['retries']++;
                            
                            // 等待重试延迟
                            $retryDelay = $this->config['retry_delay'];
                            if ($retryDelay > 0) {
                                usleep((int)($retryDelay * 1000000));
                            }
                            
                            // 重新创建Fiber并启动任务
                            $this->releaseFiber($fiber);
                            $newFiber = $this->getFiber();
                            $newFiber->start($runnable);
                            $fibers[$key]['fiber'] = $newFiber;
                            $startTimes[$key] = microtime(true);
                        } else {
                            // 达到最大重试次数，记录错误
                            $results[$key] = $e;
                            
                            // 触发任务失败事件
                            if ($this->config['onTaskFail']) {
                                ($this->config['onTaskFail'])($runnable, $e);
                            }
                            
                            $this->releaseFiber($fiber);
                            unset($fibers[$key]);
                        }
                    }
                }
            }
            
            // 让出一点时间给其他进程
            if (!empty($fibers)) {
                usleep(100);
            }
        }
        
        return $results;
    }

    /**
     * 准备任务对象
     *
     * @param callable|Runnable|string $task
     * @param array $args
     * @param float|null $timeout
     * @return Runnable
     */
    protected function prepareTask(callable|Runnable|string $task, array $args = [], ?float $timeout = null): Runnable
    {
        // 如果已经是Runnable对象，直接返回
        if ($task instanceof Runnable) {
            return $task;
        }
        
        // 如果是字符串，尝试实例化对应的类
        if (is_string($task) && class_exists($task)) {
            if (is_subclass_of($task, Runnable::class)) {
                return new $task(...$args);
            } else {
                throw new RuntimeException(sprintf('Class %s does not implement Runnable interface', $task));
            }
        }
        
        // 如果是callable，包装成Task对象
        if (is_callable($task)) {
            return Task::make($task, ['timeout' => $timeout]);
        }
        
        throw new RuntimeException('Task must be callable or implement Runnable interface');
    }

    /**
     * 获取一个Fiber实例
     *
     * @return Fiber
     * @throws RuntimeException
     */
    protected function getFiber(): Fiber
    {
        $this->gc();
        
        while (!empty($this->available)) {
            $fiber = array_pop($this->available);
            if (!$fiber->isTerminated()) {
                $this->running[spl_object_id($fiber)] = $fiber;
                if ($this->config['onCreate']) {
                    ($this->config['onCreate'])(spl_object_id($fiber));
                }
                return $fiber;
            }
            if ($this->config['onDestroy']) {
                ($this->config['onDestroy'])(spl_object_id($fiber));
            }
        }
        
        if (count($this->running) < $this->size) {
            $fiber = $this->createFiber();
            $this->running[spl_object_id($fiber)] = $fiber;
            if ($this->config['onCreate']) {
                ($this->config['onCreate'])(spl_object_id($fiber));
            }
            return $fiber;
        }
        
        throw new RuntimeException('Fiber pool is full');
    }

    /**
     * 创建一个新的Fiber实例
     *
     * @return Fiber
     */
    protected function createFiber(): Fiber
    {
        $context = $this->config['context'];
        
        return new Fiber(function (Runnable $task) use ($context) {
            try {
                if (!empty($context)) {
                    Context::merge($context);
                }
                
                return $task->run();
            } catch (\Throwable $e) {
                throw $e;
            } finally {
                Context::clear();
            }
        });
    }

    /**
     * 释放Fiber实例
     *
     * @param Fiber $fiber
     * @return void
     */
    protected function releaseFiber(Fiber $fiber): void
    {
        $id = spl_object_id($fiber);
        
        if (isset($this->running[$id])) {
            unset($this->running[$id]);
        }
        
        if ($fiber->isTerminated()) {
            if ($this->config['onDestroy']) {
                ($this->config['onDestroy'])($id);
            }
            return;
        }
        
        try {
            $fiber->throw(new FiberException('Fiber was released'));
        } catch (\Throwable $e) {
        }
        
        if (count($this->available) < $this->size) {
            $this->available[] = $fiber;
        } elseif ($this->config['onDestroy']) {
            ($this->config['onDestroy'])($id);
        }
    }

    /**
     * 执行垃圾回收
     *
     * @return void
     */
    protected function gc(): void
    {
        $this->taskCounter++;
        
        if ($this->taskCounter % $this->config['gc_interval'] === 0) {
            // 清理已终止的Fiber
            $this->available = array_filter($this->available, function ($fiber) {
                return $fiber->isTerminated();
            });
            
            // 内存回收
            gc_collect_cycles();
        }
    }

    /**
     * 获取池配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 获取池大小
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * 获取活跃纤程数量
     *
     * @return int
     */
    public function getActiveCount(): int
    {
        return count($this->running);
    }

    /**
     * 获取可用纤程数量
     *
     * @return int
     */
    public function getAvailableCount(): int
    {
        return count($this->available);
    }

    /**
     * 获取总执行任务数
     *
     * @return int
     */
    public function getTotalExecuted(): int
    {
        return $this->taskCounter;
    }

    /**
     * 获取池名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->config['name'];
    }

    /**
     * 设置上下文数据
     *
     * @param array $context
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->config['context'] = $context;
        return $this;
    }

    /**
     * 获取上下文数据
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->config['context'];
    }

    /**
     * 获取统计信息
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * 重置统计信息
     *
     * @return self
     */
    public function resetStats(): self
    {
        $this->stats = [
            'success' => 0,
            'failed' => 0,
            'retries' => 0,
            'timeouts' => 0,
            'total_execution_time' => 0
        ];
        return $this;
    }
}
