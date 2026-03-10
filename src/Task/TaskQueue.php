<?php

declare(strict_types=1);

namespace Kode\Fibers\Task;

use Kode\Fibers\Contracts\Runnable;
use Kode\Fibers\Exceptions\FiberException;
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Attributes\FiberSafe;
use SplPriorityQueue;

/**
 * Task queue implementation
 * 
 * Manages a queue of tasks with support for prioritization, 
 * concurrency control, and task lifecycle management.
 */
#[FiberSafe]
class TaskQueue
{
    /**
     * Task queue
     *
     * @var SplPriorityQueue
     */
    protected SplPriorityQueue $queue;
    
    /**
     * Fiber pool for concurrent execution
     *
     * @var FiberPool
     */
    protected FiberPool $pool;
    
    /**
     * Queue options
     *
     * @var array
     */
    protected array $options;
    
    /**
     * Running state
     *
     * @var bool
     */
    protected bool $running = false;
    
    /**
     * Paused state
     *
     * @var bool
     */
    protected bool $paused = false;
    
    /**
     * List of running tasks
     *
     * @var array
     */
    protected array $runningTasks = [];

    /**
     * TaskQueue constructor
     *
     * @param array $options Queue configuration
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'concurrency' => 1,
            'max_size' => null, // Unlimited by default
            'auto_start' => false,
        ], $options);
        
        // Create a priority queue with highest priority first
        $this->queue = new SplPriorityQueue();
        $this->queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        
        // Create fiber pool
        $this->pool = new FiberPool([
            'size' => $this->options['concurrency'],
            'name' => 'task_queue_' . uniqid(),
        ]);
        
        // Auto-start if enabled
        if ($this->options['auto_start']) {
            $this->start();
        }
    }

    /**
     * Add a task to the queue
     *
     * @param callable|Runnable $task
     * @param int $priority Lower numbers mean higher priority
     * @param array $options Task options
     * @return string Task ID
     * @throws FiberException If queue is full
     */
    public function add(callable|Runnable $task, int $priority = 0, array $options = []): string
    {
        // Check if queue size is limited
        if ($this->options['max_size'] !== null && $this->queue->count() >= $this->options['max_size']) {
            throw new FiberException('Task queue is full');
        }
        
        // Convert callable to Task if needed
        if (!($task instanceof Runnable)) {
            $task = Task::make($task, $options);
        }
        
        // Get task ID
        $taskId = $task instanceof Task ? $task->getId() : uniqid('task_', true);
        
        // Add to queue with priority
        $this->queue->insert($task, -$priority);
        
        // If queue is running and not paused, process the queue
        if ($this->running && !$this->paused) {
            $this->process();
        }
        
        return $taskId;
    }

    /**
     * Process the queue
     *
     * @return void
     */
    protected function process(): void
    {
        // Process tasks while there are tasks in the queue and slots available in the pool
        while (!$this->queue->isEmpty() && count($this->runningTasks) < $this->options['concurrency']) {
            // Get next task
            $queueItem = $this->queue->extract();
            $task = $queueItem['data'];
            // Generate task ID if needed
            $taskId = $task instanceof Task ? $task->getId() : uniqid('task_', true);
            
            // Execute task in the fiber pool
            $this->runningTasks[$taskId] = true;
            
            $this->pool->run(function () use ($task, $taskId) {
                try {
                    // Execute the task
                    $task->run();
                } catch (\Throwable $e) {
                    // Log the exception
                    $this->handleTaskException($taskId, $e);
                } finally {
                    // Remove from running tasks
                    unset($this->runningTasks[$taskId]);
                }
            });
        }
    }
    
    /**
     * Handle task exceptions
     *
     * @param string $taskId
     * @param \Throwable $e
     * @return void
     */
    protected function handleTaskException(string $taskId, \Throwable $e): void
    {
        // Default implementation: log the error
        // In a real application, you might want to integrate with a logging system
        error_log("Task {$taskId} failed: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }

    /**
     * Start processing the queue
     *
     * @return self
     */
    public function start(): self
    {
        $this->running = true;
        $this->paused = false;
        $this->process();
        return $this;
    }

    /**
     * Pause processing the queue
     *
     * @return self
     */
    public function pause(): self
    {
        $this->paused = true;
        return $this;
    }

    /**
     * Resume processing the queue
     *
     * @return self
     */
    public function resume(): self
    {
        if ($this->running && $this->paused) {
            $this->paused = false;
            $this->process();
        }
        return $this;
    }

    /**
     * Stop processing the queue
     *
     * @param bool $wait Whether to wait for running tasks to complete
     * @return self
     */
    public function stop(bool $wait = false): self
    {
        $this->running = false;
        $this->paused = false;
        
        if ($wait) {
            // Wait for all running tasks to complete
            while (!empty($this->runningTasks)) {
                usleep(100);
            }
        }
        
        return $this;
    }

    /**
     * Clear all tasks from the queue
     *
     * @return self
     */
    public function clear(): self
    {
        // Create a new empty queue
        $this->queue = new SplPriorityQueue();
        $this->queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        return $this;
    }

    /**
     * Get the number of tasks in the queue
     *
     * @return int
     */
    public function count(): int
    {
        return $this->queue->count();
    }

    /**
     * Get the number of running tasks
     *
     * @return int
     */
    public function runningCount(): int
    {
        return count($this->runningTasks);
    }

    /**
     * Get the queue status
     *
     * @return array
     */
    public function status(): array
    {
        return [
            'running' => $this->running,
            'paused' => $this->paused,
            'queue_size' => $this->count(),
            'running_tasks' => $this->runningCount(),
            'max_concurrency' => $this->options['concurrency'],
        ];
    }

    /**
     * Set the maximum concurrency
     *
     * @param int $concurrency
     * @return self
     */
    public function setConcurrency(int $concurrency): self
    {
        if ($concurrency < 1) {
            throw new FiberException('Concurrency must be at least 1');
        }
        
        $this->options['concurrency'] = $concurrency;
        
        // Reconfigure the fiber pool
        $this->pool = new FiberPool([
            'size' => $this->options['concurrency'],
            'name' => $this->pool->getName(),
        ]);
        
        // If queue is running, process more tasks
        if ($this->running && !$this->paused) {
            $this->process();
        }
        
        return $this;
    }

    /**
     * Set the maximum queue size
     *
     * @param int|null $maxSize Null for unlimited
     * @return self
     */
    public function setMaxSize(?int $maxSize): self
    {
        if ($maxSize !== null && $maxSize < 1) {
            throw new FiberException('Max size must be at least 1 or null for unlimited');
        }
        
        $this->options['max_size'] = $maxSize;
        return $this;
    }

    /**
     * Wait until the queue is empty
     *
     * @param float $timeout Maximum time to wait in seconds
     * @return bool True if queue became empty, false if timed out
     */
    public function waitEmpty(float $timeout = null): bool
    {
        $startTime = microtime(true);
        
        while (!$this->queue->isEmpty() || !empty($this->runningTasks)) {
            // Check for timeout
            if ($timeout !== null && microtime(true) - $startTime > $timeout) {
                return false;
            }
            
            usleep(100);
        }
        
        return true;
    }

    /**
     * Create a new task queue
     *
     * @param array $options
     * @return static
     */
    public static function make(array $options = []): static
    {
        return new static($options);
    }
}
