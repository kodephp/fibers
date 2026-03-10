<?php

declare(strict_types=1);

namespace Kode\Fibers\Exceptions;

use Exception;
use Throwable;

/**
 * Fiber异常类
 */
class FiberException extends Exception
{
    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param int $code 错误代码
     * @param Throwable|null $previous 前一个异常
     */
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}