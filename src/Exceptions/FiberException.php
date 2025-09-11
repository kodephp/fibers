<?php

declare(strict_types=1);

namespace Nova\Fibers\Exceptions;

use Exception;

/**
 * 纤程异常基类
 * 
 * 所有与纤程相关的异常都应该继承此类
 */
class FiberException extends Exception
{
    /**
     * 构造函数
     * 
     * @param string $message 异常信息
     * @param int $code 异常代码
     * @param Exception|null $previous 前一个异常
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}