<?php

declare(strict_types=1);

namespace Kode\Fibers\Task;

use Kode\Fibers\Concurrency\Mutex;
use Kode\Fibers\Concurrency\Scheduler;

/**
 * 简单的互斥锁实现
 *
 * 用于在纤程环境中保护共享资源的访问。
 *
 * 与旧版本的自旋实现不同：处于事件循环内时会委托给
 * {@see Mutex} 挂起等待（不占用 CPU），只有在事件循环外
 * 才退化为带退避的轮询。
 */
class TaskMutex
{
    /**
     * 锁状态
     */
    private bool $locked = false;

    /**
     * 协程感知的底层锁
     */
    private readonly Mutex $mutex;

    public function __construct()
    {
        $this->mutex = new Mutex();
    }

    /**
     * 加锁
     *
     * @param float|null $timeout 超时时间（秒），null表示无限等待
     * @return bool 是否成功获取锁
     */
    public function lock(?float $timeout = null): bool
    {
        if (Scheduler::inCoroutine()) {
            try {
                $this->mutex->lock($timeout);
            } catch (\Throwable) {
                return false;
            }

            $this->locked = true;

            return true;
        }

        $deadline = $timeout === null ? null : Scheduler::now() + $timeout;

        while (true) {
            if ($this->mutex->tryLock()) {
                $this->locked = true;

                return true;
            }

            if ($deadline !== null && Scheduler::now() >= $deadline) {
                return false;
            }

            usleep(200);
        }
    }

    /**
     * 尝试获取锁，不阻塞
     *
     * @return bool 是否成功获取锁
     */
    public function tryLock(): bool
    {
        if ($this->mutex->tryLock()) {
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
        if (!$this->locked) {
            return;
        }

        $this->locked = false;
        $this->mutex->unlock();
    }

    /**
     * 检查是否已加锁
     */
    public function isLocked(): bool
    {
        return $this->mutex->isLocked();
    }
}
