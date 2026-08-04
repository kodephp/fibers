<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Kode\Fibers\Exceptions\FiberException;

/**
 * 检测到死锁时抛出
 *
 * 当事件循环中已无任何就绪任务与待触发定时器，却仍有协程处于等待状态时，
 * 说明这些协程只可能相互等待，永远不会被唤醒。
 */
class DeadlockException extends FiberException
{
}
