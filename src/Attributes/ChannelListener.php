<?php

declare(strict_types=1);

namespace Nova\Fibers\Attributes;

use Attribute;

/**
 * 通道监听器属性
 * 
 * 用于标记方法作为特定通道的监听器，当通道中有数据时会自动调用该方法。
 * 
 * @Annotation
 * @Target({"METHOD"})
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ChannelListener
{
    /**
     * 构造函数
     * 
     * @param string $channel 通道名称
     * @param string|null $event 事件名称（可选）
     */
    public function __construct(
        public readonly string $channel,
        public readonly ?string $event = null
    ) {
    }
}