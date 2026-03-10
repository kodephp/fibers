<?php

declare(strict_types=1);

namespace Kode\Fibers\Attributes;

use Attribute;
use Kode\Attributes\ChannelListener as BaseChannelListener;

/**
 * ChannelListener attribute for automatically registering channel listeners
 *
 * This attribute marks a method to be automatically registered as a channel listener
 * when the class is instantiated through the Fiber container.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ChannelListener extends BaseChannelListener
{
    // 使用Kode\Attributes包提供的完整功能
}