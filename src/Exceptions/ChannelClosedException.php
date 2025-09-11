<?php

declare(strict_types=1);

namespace Nova\Fibers\Exceptions;

/**
 * 通道关闭异常类
 * 
 * 当尝试向已关闭的通道发送或接收数据时抛出此异常
 */
class ChannelClosedException extends FiberException
{
    /**
     * 通道名称
     * 
     * @var string
     */
    private string $channelName;

    /**
     * 构造函数
     * 
     * @param string $channelName 通道名称
     * @param string $message 异常信息
     * @param int $code 异常代码
     * @param \Exception|null $previous 前一个异常
     */
    public function __construct(
        string $channelName,
        string $message = "",
        int $code = 0,
        ?\Exception $previous = null
    ) {
        $this->channelName = $channelName;
        
        if (empty($message)) {
            $message = "Channel '{$channelName}' is closed";
        }
        
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取通道名称
     * 
     * @return string 通道名称
     */
    public function getChannelName(): string
    {
        return $this->channelName;
    }
}