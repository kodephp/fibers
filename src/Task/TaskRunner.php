<?php

declare(strict_types=1);

namespace Kode\Fibers\Task;

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Contracts\Runnable;
use Kode\Fibers\Exceptions\FiberException;
use Kode\Fibers\Attributes\Timeout;
use Kode\Context\Context;

/**
 * Task runner for fiber tasks
 * 
 * Provides enhanced task execution capabilities including timeout management,
 * retry mechanism, priority tasks, and graceful PHP version compatibility.
 */
class TaskRunner
{
    /**
     * The threshold for considering a PHP version as supporting safe destruct in fibers
     */
    private const PHP_VERSION_WITH_SAFE_DESTRUCT = 80400;
    
    /**
     * Run a task
     *
     * @param callable|Runnable $task
     * @param float|null $timeout
     * @param array $context Optional context data
     * @return mixed
     * @throws FiberException If task execution fails
     */
    public static function run(callable|Runnable $task, ?float $timeout = null, array $context = []): mixed
    {
        try {
            if ($timeout !== null) {
                // Wrap the task with timeout logic
                return static::runWithTimeout($task, $timeout, $context);
            }

            // If task is Runnable, call its run method
            if ($task instanceof Runnable) {
                return $task->run();
            }

            // Otherwise directly call the callable
            return $task();
        } catch (\Throwable $e) {
            throw new FiberException("Task execution failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Run a task with timeout
     *
     * @param callable|Runnable $task
     * @param float $timeout
     * @param array $context Optional context data
     * @return mixed
     * @throws FiberException If the task exceeds the timeout or fails
     */
    public static function runWithTimeout(callable|Runnable $task, float $timeout, array $context = []): mixed
    {
        $startTime = microtime(true);

        try {
            if (!empty($context)) {
                Context::merge($context);
            }

            $result = $task instanceof Runnable ? $task->run() : $task();
        } catch (\Throwable $e) {
            throw new FiberException("Task execution failed: " . $e->getMessage(), 0, $e);
        } finally {
            Context::clear();
        }

        $elapsedTime = microtime(true) - $startTime;
        if ($elapsedTime > $timeout) {
            if (PHP_VERSION_ID < self::PHP_VERSION_WITH_SAFE_DESTRUCT) {
                throw new FiberException(
                    "Task exceeded timeout of {$timeout} seconds (elapsed: {$elapsedTime}s). " .
                    "PHP < 8.4 无法强制中断执行中的纤程。"
                );
            }

            throw new FiberException("Task exceeded timeout of {$timeout} seconds (elapsed: {$elapsedTime}s)");
        }

        return $result;
    }

    /**
     * Run a task with retry mechanism
     *
     * @param callable|Runnable $task
     * @param int $maxRetries Maximum number of retries
     * @param float $retryDelay Delay between retries in seconds
     * @param ?float $timeout Optional timeout per attempt
     * @return mixed
     * @throws FiberException If task fails after all retries
     */
    public static function retry(
        callable|Runnable $task, 
        int $maxRetries = 3, 
        float $retryDelay = 1, 
        ?float $timeout = null
    ): mixed {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt <= $maxRetries) {
            try {
                $attempt++;
                
                if ($attempt > 1) {
                    // Wait before retrying
                    usleep((int)($retryDelay * 1000000));
                }
                
                return static::run($task, $timeout);
            } catch (\Throwable $e) {
                $lastException = $e;
                
                // If max retries reached, throw the last exception
                if ($attempt > $maxRetries) {
                    throw new FiberException(
                        "Task failed after {$maxRetries} retries: " . $e->getMessage(), 
                        (int)$e->getCode(), 
                        $e
                    );
                }
            }
        }
        
        // This should never be reached due to the throw in the loop
        throw new FiberException("Task failed after maximum retries", 0, $lastException);
    }

    /**
     * Run multiple tasks concurrently
     *
     * @param array $tasks
     * @param array $options
     * @return array
     * @throws FiberException If concurrent execution fails
     */
    public static function concurrent(array $tasks, array $options = []): array
    {
        try {
            $pool = new FiberPool($options);
            return $pool->concurrent($tasks);
        } catch (\Throwable $e) {
            throw new FiberException("Concurrent task execution failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Run tasks with priorities
     *
     * @param array $priorityTasks Array of [priority => task, ...]
     * @param array $options
     * @return array
     * @throws FiberException If priority execution fails
     */
    public static function prioritized(array $priorityTasks, array $options = []): array
    {
        try {
            // Sort tasks by priority (lower number = higher priority)
            ksort($priorityTasks);
            
            // Create a fiber pool
            $pool = new FiberPool($options);
            
            // Execute tasks in order of priority
            $results = [];
            foreach ($priorityTasks as $priority => $task) {
                $results[$priority] = $pool->run($task);
            }
            
            return $results;
        } catch (\Throwable $e) {
            throw new FiberException("Prioritized task execution failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a cancellable task
     *
     * @param callable|Runnable $task
     * @param callable $cancelCallback Optional callback to be executed when cancelled
     * @return array [runTask, cancelTask] where runTask is a callable to run the task and cancelTask is a callable to cancel it
     */
    public static function cancellable(
        callable|Runnable $task,
        ?callable $cancelCallback = null
    ): array {
        $cancelled = false;
        $cancelMutex = new TaskMutex();
        
        // Wrapped task that checks for cancellation
        $wrappedTask = function () use ($task, &$cancelled, $cancelMutex) {
            // Check if cancellation was requested before starting
            $cancelMutex->lock();
            $isCancelled = $cancelled;
            $cancelMutex->unlock();
            
            if ($isCancelled) {
                throw new FiberException("Task was cancelled before execution");
            }
            
            // Execute the task
            return static::run($task);
        };
        
        // Cancel function
        $cancel = function () use (&$cancelled, $cancelMutex, $cancelCallback) {
            $cancelMutex->lock();
            $cancelled = true;
            $cancelMutex->unlock();
            
            // Execute cancel callback if provided
            if ($cancelCallback) {
                try {
                    $cancelCallback();
                } catch (\Throwable $e) {
                    // Silently ignore errors in cancel callback
                }
            }
        };
        
        return [
            'runTask' => fn(?float $timeout = null) => static::run($wrappedTask, $timeout),
            'cancelTask' => $cancel
        ];
    }
}

/**
 * 简单的互斥锁实现
 * 
 * 用于在纤程环境中保护共享资源的访问
 */
class TaskMutex
{
    /**
     * @var bool 锁状态
     */
    private bool $locked = false;
    
    /**
     * 加锁
     * 
     * @param float|null $timeout 超时时间（秒），null表示无限等待
     * @return bool 是否成功获取锁
     */
    public function lock(?float $timeout = null): bool
    {
        $startTime = microtime(true);
        
        while (true) {
            if (!$this->locked) {
                $this->locked = true;
                return true;
            }
            
            if ($timeout !== null) {
                $elapsed = microtime(true) - $startTime;
                if ($elapsed >= $timeout) {
                    return false;
                }
            }
            
            // 让出CPU时间片，避免CPU占用过高
            usleep(100);
        }
    }
    
    /**
     * 尝试获取锁，不阻塞
     * 
     * @return bool 是否成功获取锁
     */
    public function tryLock(): bool
    {
        if (!$this->locked) {
            $this->locked = true;
            return true;
        }
        return false;
    }
    
    /**
     * 解锁
     */
    public function unlock(): void
    {
        $this->locked = false;
    }
    
    /**
     * 检查是否已加锁
     * 
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }
}
