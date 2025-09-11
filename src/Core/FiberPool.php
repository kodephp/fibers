<?php

declare(strict_types=1);

namespace Nova\Fibers\Core;

use Nova\Fibers\Contracts\Runnable;
use Nova\Fibers\Support\CpuInfo;
use Nova\Fibers\Support\Environment;
use RuntimeException;
use Fiber;
use Closure;

/**
 * 高性能 Fiber 池实现
 *
 * @package Nova\Fibers\Core
 */
class FiberPool
{
    /**
     * @var Fiber[] 活跃的纤程列表
     */
    protected array $fibers = [];

    /**
     * @var callable[] 待执行的任务队列
     */
    protected array $queue = [];

    /**
     * @var int 池大小
     */
    protected int $size;

    /**
     * @var int 最大执行时间（秒）
     */
    protected int $maxExecTime;

    /**
     * @var int GC 间隔
     */
    protected int $gcInterval;

    /**
     * @var int 已执行任务计数
     */
    protected int $execCount = 0;

    /**
     * @var callable|null 创建纤程时的回调
     */
    protected $onCreate;

    /**
     * @var callable|null 销毁纤程时的回调
     */
    protected $onDestroy;

    /**
     * @var string 池名称
     */
    protected string $name;

    /**
     * FiberPool 构造函数
     *
     * @param array $options 配置选项
     */
    public function __construct(array $options = [])
    {
        // 检查环境兼容性
        if (!Environment::checkFiberSupport()) {
            throw new RuntimeException('Fiber requires PHP 8.1 or higher. Current version: ' . PHP_VERSION);
        }

        $this->size = $options['size'] ?? (CpuInfo::get() * 4);
        $this->maxExecTime = $options['max_exec_time'] ?? 30;
        $this->gcInterval = $options['gc_interval'] ?? 100;
        $this->onCreate = $options['onCreate'] ?? null;
        $this->onDestroy = $options['onDestroy'] ?? null;
        $this->name = $options['name'] ?? 'default';
    }

    /**
     * 并行执行多个任务
     *
     * @param callable[] $tasks 任务数组
     * @param float|null $timeout 超时时间（秒）
     * @return array 结果数组
     * @throws RuntimeException 如果超时
     */
    public function concurrent(array $tasks, ?float $timeout = null): array
    {
        $results = [];
        $fibers = [];
        $terminated = [];

        // 如果设置了超时，记录开始时间
        $startTime = $timeout !== null ? microtime(true) : null;

        // 创建纤程
        foreach ($tasks as $key => $task) {
            $fiber = new Fiber($task);
            $fibers[$key] = $fiber;
            $fiber->start();
            $terminated[$key] = false;
        }

        // 等待所有纤程完成
        while (count(array_filter($terminated)) < count($fibers)) {
            // 检查是否超时
            if ($timeout !== null && $startTime !== null && (microtime(true) - $startTime) > $timeout) {
                // 清理所有纤程
                foreach ($fibers as $fiber) {
                    // 注意：PHP Fiber 无法强制终止运行中的纤程，我们只能抛出异常
                }
                throw new RuntimeException("Task execution timed out after {$timeout} seconds");
            }

            foreach ($fibers as $key => $fiber) {
                if (!$terminated[$key]) {
                    if ($fiber->isTerminated()) {
                        $terminated[$key] = true;
                        try {
                            $results[$key] = $fiber->getReturn();
                        } catch (\Throwable $e) {
                            $results[$key] = $e;
                        }
                    } else {
                        // 给其他纤程一些执行时间
                        Fiber::suspend();
                    }
                }
            }
        }

        return $results;
    }

    /**
     * 提交任务到池中执行
     *
     * @param callable $task 任务
     * @return mixed 任务结果
     */
    public function submit(callable $task)
    {
        $fiber = new Fiber($task);
        $this->fibers[] = $fiber;

        if ($this->onCreate) {
            ($this->onCreate)(count($this->fibers) - 1);
        }

        $fiber->start();

        try {
            $result = $fiber->getReturn();
            $this->cleanupFiber($fiber);
            return $result;
        } catch (\Throwable $e) {
            $this->cleanupFiber($fiber);
            throw $e;
        }
    }

    /**
     * 清理完成的纤程
     *
     * @param Fiber $fiber
     * @return void
     */
    protected function cleanupFiber(Fiber $fiber): void
    {
        $index = array_search($fiber, $this->fibers, true);
        if ($index !== false) {
            if ($this->onDestroy) {
                ($this->onDestroy)($index);
            }
            unset($this->fibers[$index]);
            $this->fibers = array_values($this->fibers); // 重新索引
        }

        // 执行垃圾回收
        $this->execCount++;
        if ($this->execCount % $this->gcInterval === 0) {
            $this->gc();
        }
    }

    /**
     * 垃圾回收
     *
     * @return void
     */
    protected function gc(): void
    {
        // 清理已完成的纤程
        $this->fibers = array_filter($this->fibers, function ($fiber) {
            return $fiber->isTerminated() === false;
        });
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
        return count($this->fibers);
    }
}
