<?php

namespace Nova\Fibers\Attribute;

use Attribute;

/**
 * ChannelListener - 通道监听器注解
 * 
 * 用于标记方法作为特定通道的监听器
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ChannelListener
{
    /**
     * @param string $channel 通道名称
     * @param string|null $description 描述信息
     */
    public function __construct(
        public string $channel,
        public ?string $description = null
    ) {}
}