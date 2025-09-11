<?php

declare(strict_types=1);

namespace Nova\Fibers\Exceptions;

/**
 * 超时异常类
 * 
 * 当纤程任务执行超时时抛出此异常
 */
class TimeoutException extends FiberException
{
    /**
     * 超时时间（秒）
     * 
     * @var float
     */
    private float $timeout;

    /**
     * 构造函数
     * 
     * @param float $timeout 超时时间（秒）
     * @param string $message 异常信息
     * @param int $code 异常代码
     * @param \Exception|null $previous 前一个异常
     */
    public function __construct(
        float $timeout,
        string $message = "",
        int $code = 0,
        ?\Exception $previous = null
    ) {
        $this->timeout = $timeout;
        
        if (empty($message)) {
            $message = "Task execution timed out after {$timeout} seconds";
        }
        
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取超时时间
     * 
     * @return float 超时时间（秒）
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }
}