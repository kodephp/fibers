<?php

declare(strict_types=1);

namespace Kode\Fibers\Task;

use Kode\Fibers\Contracts\Runnable;
use Kode\Fibers\Exceptions\FiberException;
use Kode\Fibers\Attributes\FiberSafe;

/**
 * Retryable task implementation
 * 
 * A specialized task that automatically retries execution on failure
 * according to configurable retry policies.
 */
#[FiberSafe]
class RetryableTask implements Runnable
{
    /**
     * Task callable
     *
     * @var callable
     */
    protected $callable;
    
    /**
     * Maximum number of retries
     *
     * @var int
     */
    protected int $maxRetries;
    
    /**
     * Delay between retries in seconds
     *
     * @var float
     */
    protected float $retryDelay;
    
    /**
     * Task options
     *
     * @var array
     */
    protected array $options;
    
    /**
     * List of exceptions that should trigger a retry
     *
     * @var array
     */
    protected array $retryableExceptions = [];
    
    /**
     * List of exceptions that should NOT trigger a retry
     *
     * @var array
     */
    protected array $nonRetryableExceptions = [];
    
    /**
     * Optional backoff strategy function
     *
     * @var callable|null
     */
    protected $backoffStrategy = null;

    /**
     * RetryableTask constructor
     *
     * @param callable $callable
     * @param int $maxRetries
     * @param float $retryDelay
     * @param array $options
     */
    public function __construct(
        callable $callable,
        int $maxRetries = 3,
        float $retryDelay = 1,
        array $options = []
    ) {
        $this->callable = $callable;
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
        $this->options = array_merge([
            'id' => uniqid('retry_task_', true),
            'context' => [],
            'priority' => 0,
            'timeout' => null,
        ], $options);
    }

    /**
     * Run the task with retry logic
     *
     * @return mixed
     * @throws FiberException If task fails after all retries
     */
    public function run(): mixed
    {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt <= $this->maxRetries) {
            try {
                $attempt++;
                
                if ($attempt > 1) {
                    // Calculate delay based on backoff strategy if provided
                    $delay = $this->backoffStrategy ? 
                        ($this->backoffStrategy)($attempt, $this->retryDelay) : 
                        $this->retryDelay;
                    
                    // Wait before retrying
                    usleep((int)($delay * 1000000));
                }
                
                // Execute the task
                return call_user_func($this->callable);
            } catch (\Throwable $e) {
                $lastException = $e;
                
                // Check if this exception is retryable
                if (!$this->shouldRetry($e)) {
                    throw new FiberException(
                        "Non-retryable exception occurred in task {$this->options['id']}: " . $e->getMessage(),
                        (int)$e->getCode(),
                        $e
                    );
                }
                
                // If max retries reached, throw the last exception
                if ($attempt > $this->maxRetries) {
                    throw new FiberException(
                        "Task {$this->options['id']} failed after {$this->maxRetries} retries: " . $e->getMessage(),
                        (int)$e->getCode(),
                        $e
                    );
                }
            }
        }
        
        // This should never be reached due to the throw in the loop
        throw new FiberException("Task {$this->options['id']} failed after maximum retries", 0, $lastException);
    }
    
    /**
     * Determine if an exception should trigger a retry
     *
     * @param \Throwable $e
     * @return bool
     */
    protected function shouldRetry(\Throwable $e): bool
    {
        $exceptionClass = get_class($e);
        
        // If there are specific retryable exceptions defined, check against them
        if (!empty($this->retryableExceptions)) {
            foreach ($this->retryableExceptions as $retryableException) {
                if ($e instanceof $retryableException) {
                    return true;
                }
            }
            return false;
        }
        
        // If there are specific non-retryable exceptions defined, check against them
        if (!empty($this->nonRetryableExceptions)) {
            foreach ($this->nonRetryableExceptions as $nonRetryableException) {
                if ($e instanceof $nonRetryableException) {
                    return false;
                }
            }
        }
        
        // Default behavior: retry on all exceptions
        return true;
    }
    
    /**
     * Add retryable exception types
     *
     * @param string ...$exceptionClasses
     * @return self
     */
    public function retryOn(string ...$exceptionClasses): self
    {
        $this->retryableExceptions = array_merge($this->retryableExceptions, $exceptionClasses);
        return $this;
    }
    
    /**
     * Add non-retryable exception types
     *
     * @param string ...$exceptionClasses
     * @return self
     */
    public function doNotRetryOn(string ...$exceptionClasses): self
    {
        $this->nonRetryableExceptions = array_merge($this->nonRetryableExceptions, $exceptionClasses);
        return $this;
    }
    
    /**
     * Set a custom backoff strategy
     *
     * @param callable $strategy A function that takes (attempt, baseDelay) and returns the delay in seconds
     * @return self
     */
    public function withBackoffStrategy(callable $strategy): self
    {
        $this->backoffStrategy = $strategy;
        return $this;
    }
    
    /**
     * Apply exponential backoff
     *
     * @param float $factor Multiplication factor for each retry
     * @return self
     */
    public function withExponentialBackoff(float $factor = 2.0): self
    {
        $this->backoffStrategy = function (int $attempt, float $baseDelay) use ($factor) {
            return $baseDelay * pow($factor, $attempt - 1);
        };
        return $this;
    }
    
    /**
     * Apply linear backoff
     *
     * @param float $increment Amount to add for each retry
     * @return self
     */
    public function withLinearBackoff(float $increment = 1.0): self
    {
        $this->backoffStrategy = function (int $attempt, float $baseDelay) use ($increment) {
            return $baseDelay + ($increment * ($attempt - 1));
        };
        return $this;
    }
    
    /**
     * Apply jitter to backoff to avoid thundering herd problem
     *
     * @param float $maxJitter Maximum percentage of jitter (0-1)
     * @return self
     */
    public function withJitter(float $maxJitter = 0.2): self
    {
        $originalStrategy = $this->backoffStrategy;
        
        $this->backoffStrategy = function (int $attempt, float $baseDelay) use ($maxJitter, $originalStrategy) {
            // Calculate base delay using the original strategy or just the base delay
            $delay = $originalStrategy ? 
                ($originalStrategy)($attempt, $baseDelay) : 
                $baseDelay;
            
            // Apply jitter (randomly increase or decrease by up to maxJitter percentage)
            $jitter = $delay * $maxJitter;
            $delay += (rand(0, 200) / 100 - 1) * $jitter;
            
            return max(0, $delay); // Ensure delay is not negative
        };
        return $this;
    }
    
    /**
     * Get task ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->options['id'];
    }
    
    /**
     * Get task context
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->options['context'] ?? [];
    }
    
    /**
     * Create a retryable task from a callable
     *
     * @param callable $callable
     * @param int $maxRetries
     * @param float $retryDelay
     * @param array $options
     * @return static
     */
    public static function make(
        callable $callable,
        int $maxRetries = 3,
        float $retryDelay = 1,
        array $options = []
    ): static {
        return new static($callable, $maxRetries, $retryDelay, $options);
    }
}