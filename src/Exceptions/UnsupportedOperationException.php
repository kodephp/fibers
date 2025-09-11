<?php

declare(strict_types=1);

namespace Nova\Fibers\Exceptions;

/**
 * 不支持的操作异常类
 * 
 * 当在当前环境下尝试执行不支持的操作时抛出此异常
 */
class UnsupportedOperationException extends FiberException
{
    /**
     * 操作名称
     * 
     * @var string
     */
    private string $operation;

    /**
     * 构造函数
     * 
     * @param string $operation 操作名称
     * @param string $message 异常信息
     * @param int $code 异常代码
     * @param \Exception|null $previous 前一个异常
     */
    public function __construct(
        string $operation,
        string $message = "",
        int $code = 0,
        ?\Exception $previous = null
    ) {
        $this->operation = $operation;
        
        if (empty($message)) {
            $message = "Operation '{$operation}' is not supported in current environment";
        }
        
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取操作名称
     * 
     * @return string 操作名称
     */
    public function getOperation(): string
    {
        return $this->operation;
    }
}