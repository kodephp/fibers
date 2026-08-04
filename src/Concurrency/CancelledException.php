<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Kode\Fibers\Exceptions\FiberException;

/**
 * 协程被取消时抛出
 *
 * 由 {@see Coroutine::cancel()} 或 {@see CancellationToken} 触发。
 */
class CancelledException extends FiberException
{
}
