<?php

declare(strict_types=1);

namespace Kode\Fibers\Core;

use Kode\Context\Context;
use Kode\Fibers\Concurrency\CancelledException;
use Kode\Fibers\Concurrency\Runtime;
use Kode\Fibers\Concurrency\Scheduler;
use Kode\Fibers\Concurrency\Semaphore;
use Kode\Fibers\Contracts\Runnable;
use Kode\Fibers\Exceptions\FiberException;
use Kode\Fibers\Support\CpuInfo;
use Kode\Fibers\Task\Task;
use RuntimeException;

/**
 * Fiber池 - 管理和复用协程执行单元
 *
 * v4 起底层由 {@see Scheduler} 事件循环驱动：
 *
 * - `concurrent()` 是真并发（任务交错执行），不再是「逐个 resume」的伪并发；
 * - 超时会真正中断任务，不会出现「超时后任务仍跑完并被重试」的问题；
 * - `max_retries` 默认改为 0：旧版本默认 3 会让失败任务被静默执行 4 次。
 */
class FiberPool
{
    /**
     * 池配置
     */
    protected array $config;

    /**
     * 池大小（并发上限）
     */
    protected int $size;

    /**
     * 执行任务计数器
     */
    protected int $taskCounter = 0;

    /**
     * 当前活跃任务数
     */
    protected int $activeCount = 0;

    /**
     * 统计信息
     */
    protected array $stats = [
        'success' => 0,
        'failed' => 0,
        'retries' => 0,
        'timeouts' => 0,
        'total_execution_time' => 0,
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
            'max_retries' => 0,
            'retry_delay' => 1,
            'onCreate' => null,
            'onDestroy' => null,
            'onTaskStart' => null,
            'onTaskComplete' => null,
            'onTaskFail' => null,
            'context' => [],
            'name' => 'default',
            'strict_mode' => true,
        ], $config);

        $this->size = max(1, (int)$this->config['size']);
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
        $runnable = $this->prepareTask($task, $args, $timeout);

        $maxRetries = max(0, (int)$this->config['max_retries']);
        $retryDelay = (float)$this->config['retry_delay'];
        $attempt = 0;
        $lastError = null;

        while ($attempt <= $maxRetries) {
            if ($attempt > 0) {
                $this->stats['retries']++;

                if ($retryDelay > 0) {
                    Runtime::sleep($retryDelay);
                }
            }

            $startTime = Scheduler::now();
            $this->taskCounter++;
            $this->activeCount++;

            try {
                $this->fire('onTaskStart', $runnable);

                $result = Runtime::execute($this->wrapWithContext($runnable), $timeout);

                $this->stats['success']++;
                $this->stats['total_execution_time'] += Scheduler::now() - $startTime;

                $this->fire('onTaskComplete', $runnable, $result);

                return $result;
            } catch (\Throwable $e) {
                $this->stats['failed']++;

                if ($e instanceof CancelledException) {
                    $this->stats['timeouts']++;
                }

                $this->fire('onTaskFail', $runnable, $e);

                $lastError = $e;
                $attempt++;
            } finally {
                $this->activeCount--;
                $this->gc();
            }
        }

        throw new FiberException(
            sprintf('Task failed after %d attempts: %s', $maxRetries + 1, $lastError?->getMessage() ?? 'unknown'),
            (int)($lastError?->getCode() ?? 0),
            $lastError
        );
    }

    /**
     * 并行运行多个任务
     *
     * 失败的任务以 Throwable 形式出现在结果数组中（键与输入一致）。
     *
     * @param array $tasks 任务数组
     * @param float|null $timeout 单个任务的超时时间（秒）
     * @return array
     * @throws FiberException
     */
    public function concurrent(array $tasks, ?float $timeout = null): array
    {
        if ($tasks === []) {
            return [];
        }

        $prepared = [];

        foreach ($tasks as $key => $task) {
            try {
                $runnable = $this->prepareTask($task, [], null);
            } catch (\Throwable $e) {
                $prepared[$key] = $e;

                continue;
            }

            $prepared[$key] = $runnable;
        }

        $wrapped = [];
        $failures = [];

        foreach ($prepared as $key => $item) {
            if ($item instanceof \Throwable) {
                $failures[$key] = $item;
                $this->stats['failed']++;

                continue;
            }

            $this->fire('onTaskStart', $item);

            $body = $this->wrapWithContext($item);
            $wrapped[$key] = static fn(): mixed => $body();
        }

        $this->taskCounter += count($wrapped);
        $started = Scheduler::now();

        $results = Runtime::settleAll($wrapped, $timeout, $this->size);

        foreach ($results as $key => $value) {
            $runnable = $prepared[$key];

            if ($value instanceof \Throwable) {
                $this->stats['failed']++;

                if ($value instanceof CancelledException) {
                    $this->stats['timeouts']++;
                }

                $this->fire('onTaskFail', $runnable, $value);

                continue;
            }

            $this->stats['success']++;
            $this->fire('onTaskComplete', $runnable, $value);
        }

        $this->stats['total_execution_time'] += Scheduler::now() - $started;

        // 保持与输入相同的键顺序
        $ordered = [];

        foreach ($prepared as $key => $_) {
            $ordered[$key] = $failures[$key] ?? $results[$key] ?? null;
        }

        return $ordered;
    }

    /**
     * 以受限并发批量执行任务，任一任务失败即抛出
     *
     * @param array $tasks
     * @param float|null $timeout
     * @return array
     * @throws \Throwable
     */
    public function map(array $tasks, ?float $timeout = null): array
    {
        return Runtime::all($tasks, $timeout, $this->size);
    }

    /**
     * 创建一个受本池并发上限约束的信号量
     */
    public function semaphore(): Semaphore
    {
        return new Semaphore($this->size);
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
        if ($task instanceof Runnable) {
            return $task;
        }

        if (is_string($task) && class_exists($task)) {
            if (is_subclass_of($task, Runnable::class)) {
                return new $task(...$args);
            }

            throw new RuntimeException(sprintf('Class %s does not implement Runnable interface', $task));
        }

        if (is_callable($task)) {
            return Task::make($task, ['timeout' => $timeout]);
        }

        throw new RuntimeException('Task must be callable or implement Runnable interface');
    }

    /**
     * 用池上下文包装任务
     */
    protected function wrapWithContext(Runnable $runnable): \Closure
    {
        $context = $this->config['context'];

        return static function () use ($runnable, $context): mixed {
            if ($context !== []) {
                Context::merge($context);
            }

            return $runnable->run();
        };
    }

    /**
     * 触发生命周期回调
     */
    protected function fire(string $hook, mixed ...$args): void
    {
        $callback = $this->config[$hook] ?? null;

        if (is_callable($callback)) {
            $callback(...$args);
        }
    }

    /**
     * 周期性内存回收
     */
    protected function gc(): void
    {
        $interval = max(1, (int)$this->config['gc_interval']);

        if ($this->taskCounter > 0 && $this->taskCounter % $interval === 0) {
            gc_collect_cycles();
        }
    }

    /**
     * 获取池配置
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 获取池大小
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * 获取活跃纤程数量
     */
    public function getActiveCount(): int
    {
        return $this->activeCount;
    }

    /**
     * 获取可用纤程数量
     */
    public function getAvailableCount(): int
    {
        return max(0, $this->size - $this->activeCount);
    }

    /**
     * 获取总执行任务数
     */
    public function getTotalExecuted(): int
    {
        return $this->taskCounter;
    }

    /**
     * 获取池名称
     */
    public function getName(): string
    {
        return (string)$this->config['name'];
    }

    /**
     * 设置上下文数据
     */
    public function setContext(array $context): self
    {
        $this->config['context'] = $context;

        return $this;
    }

    /**
     * 获取上下文数据
     */
    public function getContext(): array
    {
        return $this->config['context'];
    }

    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): self
    {
        $this->stats = [
            'success' => 0,
            'failed' => 0,
            'retries' => 0,
            'timeouts' => 0,
            'total_execution_time' => 0,
        ];

        return $this;
    }
}
