<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

/**
 * 等待超时时抛出
 *
 * 继承自 {@see CancelledException}，因此「超时」天然属于「取消」的一种，
 * 调用方既可以只捕获超时，也可以统一按取消处理。
 */
class TimeoutException extends CancelledException
{
}
