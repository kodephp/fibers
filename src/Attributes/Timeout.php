<?php

declare(strict_types=1);

namespace Kode\Fibers\Attributes;

use Attribute;
use Kode\Attributes\Timeout as BaseTimeout;

/**
 * Timeout attribute to set execution timeout for fiber methods
 *
 * This attribute specifies a timeout for method execution when running
 * in a fiber context. If the method exceeds the specified timeout,
 * a TimeoutException will be thrown.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Timeout extends BaseTimeout
{
    // 使用Kode\Attributes包提供的完整功能
}