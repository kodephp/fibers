<?php

declare(strict_types=1);

namespace Kode\Fibers\Attributes;

use Attribute;
use Kode\Attributes\FiberSafe as BaseFiberSafe;

/**
 * FiberSafe attribute to mark methods that can be safely called in a fiber context
 *
 * This attribute indicates that a method is safe to be called within a fiber
 * and has been tested to handle fiber suspension and resumption correctly.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class FiberSafe extends BaseFiberSafe
{
    // 使用Kode\Attributes包提供的完整功能
}