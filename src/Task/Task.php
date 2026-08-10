<?php

declare(strict_types=1);

namespace Kode\Fibers\Task;

use Kode\Context\Context;
use Kode\Fibers\Concurrency\CancelledException;
use Kode\Fibers\Concurrency\Runtime;
use Kode\Fibers\Contracts\Runnable;
use Kode\Fibers\Exceptions\FiberException;

/**
 * Task class for fiber tasks
 * 
 * Represents a unit of work that can be executed within a fiber, 
 * supporting context, timeout, and priority.
 */
class Task implements Runnable
{
    /**
     * Task callable
     *
     * @var callable
     */
    protected $callable;
    
    /**
     * Unique task ID
     *
     * @var string
     */
    protected string $id;
    
    /**
     * Task context data
     *
     * @var array
     */
    protected array $context = [];
    
    /**
     * Task priority
     * Lower numbers mean higher priority
     *
     * @var int
     */
    protected int $priority = 0;
    
    /**
     * Task timeout in seconds
     *
     * @var ?float
     */
    protected ?float $timeout = null;
    
    /**
     * Task creation time
     *
     * @var float
     */
    protected float $createdAt;

    /**
     * Task constructor
     *
     * @param callable $callable
     * @param array $options Optional task configuration
     */
    public function __construct(callable $callable, array $options = [])
    {
        $this->callable = $callable;
        $this->id = $options['id'] ?? uniqid('task_', true);
        $this->context = $options['context'] ?? [];
        $this->priority = $options['priority'] ?? 0;
        $this->timeout = $options['timeout'] ?? null;
        $this->createdAt = microtime(true);
    }

    /**
     * Run the task
     *
     * @return mixed
     * @throws FiberException If task execution fails
     */
    #[\Override]
    public function run(): mixed
    {
        $body = function (): mixed {
            if ($this->context !== []) {
                Context::merge($this->context);
            }

            return ($this->callable)();
        };

        try {
            return Runtime::execute($body, $this->timeout);
        } catch (CancelledException $e) {
            // 超时 / 取消需要保留原始类型，供上层判断是否应当重试
            throw $e;
        } catch (FiberException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new FiberException(
                "Task {$this->id} execution failed: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Get task ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }
    
    /**
     * Get task context
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }
    
    /**
     * Set task context
     *
     * @param array $context
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }
    
    /**
     * Add data to task context
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addToContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }
    
    /**
     * Get task priority
     *
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }
    
    /**
     * Set task priority
     *
     * @param int $priority
     * @return self
     */
    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }
    
    /**
     * Get task timeout
     *
     * @return ?float
     */
    public function getTimeout(): ?float
    {
        return $this->timeout;
    }
    
    /**
     * Set task timeout
     *
     * @param float $timeout
     * @return self
     */
    public function setTimeout(float $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }
    
    /**
     * Get task creation time
     *
     * @return float
     */
    public function getCreatedAt(): float
    {
        return $this->createdAt;
    }
    
    /**
     * Get task age in seconds
     *
     * @return float
     */
    public function getAge(): float
    {
        return microtime(true) - $this->createdAt;
    }
    
    /**
     * Create a new task with retry mechanism
     *
     * @param int $maxRetries
     * @param float $retryDelay
     * @return RetryableTask
     */
    public function withRetry(int $maxRetries = 3, float $retryDelay = 1): RetryableTask
    {
        return new RetryableTask($this->callable, $maxRetries, $retryDelay, [
            'id' => $this->id,
            'context' => $this->context,
            'priority' => $this->priority,
            'timeout' => $this->timeout
        ]);
    }
    
    /**
     * Create a task from a callable
     *
     * @param callable $callable
     * @param array $options
     * @return static
     */
    public static function make(callable $callable, array $options = []): static
    {
        return new static($callable, $options);
    }
}