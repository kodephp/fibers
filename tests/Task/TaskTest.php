<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests\Task;

use PHPUnit\Framework\TestCase;
use Kode\Fibers\Task\Task;
use Kode\Fibers\Task\RetryableTask;
use Kode\Fibers\Task\TaskQueue;
use Kode\Fibers\Exceptions\FiberException;
use Kode\Fibers\Contracts\Runnable;

/**
 * Tests for task classes
 */
class TaskTest extends TestCase
{
    /**
     * Test basic task execution
     */
    public function testTaskExecution()
    {
        $task = new Task(function () {
            return 'Hello, Fiber!';
        });
        
        $result = $task->run();
        
        $this->assertEquals('Hello, Fiber!', $result);
    }
    
    /**
     * Test task with context
     */
    public function testTaskWithContext()
    {
        $task = new Task(function () use (&$context) {
            return "Hello, {$context['name']}!";
        }, ['context' => ['name' => 'World']]);
        
        // Make context available to the task
        $context = $task->getContext();
        
        $result = $task->run();
        
        $this->assertEquals('Hello, World!', $result);
    }
    
    /**
     * Test task with timeout
     */
    public function testTaskWithTimeout()
    {
        $task = new Task(function () {
            usleep(100000); // Sleep for 0.1 seconds
            return 'Done';
        }, ['timeout' => 0.5]);
        
        $result = $task->run();
        
        $this->assertEquals('Done', $result);
    }
    
    /**
     * Test task that exceeds timeout
     */
    public function testTaskExceedingTimeout()
    {
        $this->expectException(FiberException::class);
        $this->expectExceptionMessageMatches('/Task .* exceeded timeout/');
        
        $task = new Task(function () {
            usleep(300000); // Sleep for 0.3 seconds
            return 'This should not be reached';
        }, ['timeout' => 0.1]);
        
        $task->run();
    }
    
    /**
     * Test task priority
     */
    public function testTaskPriority()
    {
        $task1 = new Task(function () { return 1; }, ['priority' => 10]);
        $task2 = new Task(function () { return 2; }, ['priority' => 1]);
        
        $this->assertEquals(10, $task1->getPriority());
        $this->assertEquals(1, $task2->getPriority());
        
        // Change priority
        $task1->setPriority(5);
        $this->assertEquals(5, $task1->getPriority());
    }
    
    /**
     * Test task ID and age
     */
    public function testTaskIdAndAge()
    {
        $task = new Task(function () { return true; });
        
        $this->assertNotNull($task->getId());
        $this->assertStringStartsWith('task_', $task->getId());
        
        // Check age is a positive number
        $this->assertGreaterThan(0, $task->getAge());
        
        // Check created at is a float
        $this->assertIsFloat($task->getCreatedAt());
    }
    
    /**
     * Test static make method
     */
    public function testTaskMakeMethod()
    {
        $task = Task::make(function () { return 'Made by make'; });
        
        $this->assertInstanceOf(Task::class, $task);
        $this->assertEquals('Made by make', $task->run());
    }
    
    /**
     * Test retryable task creation
     */
    public function testRetryableTaskCreation()
    {
        $task = new Task(function () { return 'Test'; });
        $retryableTask = $task->withRetry(3, 0.1);
        
        $this->assertInstanceOf(RetryableTask::class, $retryableTask);
        $this->assertEquals('Test', $retryableTask->run());
    }
    
    /**
     * Test retryable task with successful retry
     */
    public function testRetryableTaskWithRetry()
    {
        $counter = 0;
        
        $task = new Task(function () use (&$counter) {
            $counter++;
            if ($counter <= 2) {
                throw new \Exception('Simulated failure');
            }
            return 'Success after ' . $counter . ' attempts';
        });
        
        $retryableTask = $task->withRetry(3, 0.01);
        $result = $retryableTask->run();
        
        $this->assertEquals('Success after 3 attempts', $result);
        $this->assertEquals(3, $counter);
    }
    
    /**
     * Test retryable task with max retries exceeded
     */
    public function testRetryableTaskWithMaxRetriesExceeded()
    {
        $this->expectException(FiberException::class);
        $this->expectExceptionMessageMatches('/failed after 2 retries/');
        
        $counter = 0;
        
        $task = new Task(function () use (&$counter) {
            $counter++;
            throw new \Exception('Persistent failure');
        });
        
        $retryableTask = $task->withRetry(2, 0.01);
        $retryableTask->run();
    }
    
    /**
     * Test retryable task with specific retryable exceptions
     */
    public function testRetryableTaskWithSpecificExceptions()
    {
        $counter = 0;
        
        $task = new Task(function () use (&$counter) {
            $counter++;
            if ($counter <= 1) {
                throw new \RuntimeException('Retryable error');
            }
            throw new \InvalidArgumentException('Non-retryable error');
        });
        
        $retryableTask = $task->withRetry(3, 0.01)
            ->retryOn(\RuntimeException::class)
            ->doNotRetryOn(\InvalidArgumentException::class);
        
        try {
            $retryableTask->run();
            $this->fail('Expected FiberException was not thrown');
        } catch (FiberException $e) {
            $this->assertStringContainsString('Non-retryable exception', $e->getMessage());
            $this->assertEquals(2, $counter); // Should retry once, then fail on the second attempt
        }
    }
    
    /**
     * Test retryable task with backoff strategies
     */
    public function testRetryableTaskWithBackoffStrategies()
    {
        $counter = 0;
        $startTime = microtime(true);
        
        $task = new Task(function () use (&$counter) {
            $counter++;
            if ($counter <= 3) {
                throw new \Exception('Simulated failure');
            }
            return 'Success';
        });
        
        // Use exponential backoff with jitter
        $retryableTask = $task->withRetry(4, 0.01)
            ->withExponentialBackoff(2.0)
            ->withJitter(0.1);
        
        $result = $retryableTask->run();
        $elapsedTime = microtime(true) - $startTime;
        
        $this->assertEquals('Success', $result);
        $this->assertEquals(4, $counter);
        
        // With exponential backoff, the total delay should be at least 0.01 + 0.02 + 0.04 = 0.07 seconds
        $this->assertGreaterThan(0.07, $elapsedTime);
    }
    
    /**
     * Test basic task queue functionality
     */
    public function testTaskQueueBasic()
    {
        $results = [];
        
        $queue = new TaskQueue(['concurrency' => 2, 'auto_start' => false]);
        
        // Add some tasks
        $queue->add(function () use (&$results) {
            $results[] = 'Task 1';
        }, 10);
        
        $queue->add(function () use (&$results) {
            $results[] = 'Task 2';
        }, 1);
        
        $queue->add(function () use (&$results) {
            $results[] = 'Task 3';
        }, 5);
        
        // Check queue size
        $this->assertEquals(3, $queue->count());
        
        // Start the queue
        $queue->start();
        
        // Wait for tasks to complete
        $queue->waitEmpty(1.0);
        
        // Check results
        $this->assertCount(3, $results);
        $this->assertContains('Task 1', $results);
        $this->assertContains('Task 2', $results);
        $this->assertContains('Task 3', $results);
        
        // Check queue is empty
        $this->assertEquals(0, $queue->count());
    }
    
    /**
     * Test task queue with prioritization
     */
    public function testTaskQueueWithPrioritization()
    {
        $executionOrder = [];
        
        $queue = new TaskQueue(['concurrency' => 1, 'auto_start' => false]);
        
        // Add tasks with different priorities (lower number = higher priority)
        $queue->add(function () use (&$executionOrder) {
            $executionOrder[] = 'Low priority';
        }, 10);
        
        $queue->add(function () use (&$executionOrder) {
            $executionOrder[] = 'High priority';
        }, 1);
        
        $queue->add(function () use (&$executionOrder) {
            $executionOrder[] = 'Medium priority';
        }, 5);
        
        // Start the queue
        $queue->start();
        
        // Wait for tasks to complete
        $queue->waitEmpty(1.0);
        
        // Check execution order (should be high, medium, low priority)
        $this->assertEquals(['High priority', 'Medium priority', 'Low priority'], $executionOrder);
    }
    
    /**
     * Test task queue pause and resume functionality
     */
    public function testTaskQueuePauseAndResume()
    {
        $results = [];
        $paused = false;
        
        $queue = new TaskQueue(['concurrency' => 1, 'auto_start' => true]);
        
        // Add a task that pauses the queue
        $queue->add(function () use (&$queue, &$paused) {
            $paused = true;
            $queue->pause();
        });
        
        // Add a task that should not execute until resumed
        $queue->add(function () use (&$results) {
            $results[] = 'Resumed task';
        });
        
        // Wait for the first task to pause the queue
        while (!$paused) {
            usleep(10);
        }
        
        // Check that the second task hasn't executed yet
        $this->assertEmpty($results);
        
        // Resume the queue
        $queue->resume();
        
        // Wait for the second task to complete
        $queue->waitEmpty(1.0);
        
        // Check that the second task executed
        $this->assertEquals(['Resumed task'], $results);
    }
    
    /**
     * Test task queue clear functionality
     */
    public function testTaskQueueClear()
    {
        $queue = new TaskQueue(['auto_start' => false]);
        
        // Add some tasks
        for ($i = 0; $i < 5; $i++) {
            $queue->add(function () use ($i) {
                return "Task $i";
            });
        }
        
        // Check queue size
        $this->assertEquals(5, $queue->count());
        
        // Clear the queue
        $queue->clear();
        
        // Check queue is empty
        $this->assertEquals(0, $queue->count());
    }
    
    /**
     * Test task queue status
     */
    public function testTaskQueueStatus()
    {
        $queue = new TaskQueue(['concurrency' => 2, 'auto_start' => false]);
        
        // Add a task
        $queue->add(function () {
            usleep(10000); // Sleep for 0.01 seconds
        });
        
        // Get status before starting
        $status = $queue->status();
        
        $this->assertFalse($status['running']);
        $this->assertFalse($status['paused']);
        $this->assertEquals(1, $status['queue_size']);
        $this->assertEquals(0, $status['running_tasks']);
        $this->assertEquals(2, $status['max_concurrency']);
        
        // Start the queue
        $queue->start();
        
        // Get status after starting
        $status = $queue->status();
        
        $this->assertTrue($status['running']);
        $this->assertFalse($status['paused']);
    }
    
    /**
     * Test task queue with max size limit
     */
    public function testTaskQueueWithMaxSize()
    {
        $this->expectException(FiberException::class);
        $this->expectExceptionMessage('Task queue is full');
        
        $queue = new TaskQueue(['max_size' => 2, 'auto_start' => false]);
        
        // Add tasks up to the limit
        $queue->add(function () { return 1; });
        $queue->add(function () { return 2; });
        
        // Try to add one more task (should throw exception)
        $queue->add(function () { return 3; });
    }
}

// Create a dedicated directory for task tests
// This allows us to organize tests better as the project grows