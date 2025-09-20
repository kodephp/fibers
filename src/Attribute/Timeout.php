<?php

namespace Nova\Fibers\Attribute;

use Attribute;

/**
 * Timeout - 超时控制注解
 * 
 * 用于标记方法或函数的执行超时时间
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Timeout
{
    /**
     * @param int $seconds 超时时间（秒）
     * @param string|null $message 超时消息
     */
    public function __construct(
        public int $seconds,
        public ?string $message = null
    ) {}
}