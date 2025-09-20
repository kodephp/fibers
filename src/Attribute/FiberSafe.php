<?php

namespace Nova\Fibers\Attribute;

use Attribute;

/**
 * FiberSafe - 纤程安全标记
 * 
 * 标记一个方法或函数可以在纤程环境中安全调用
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class FiberSafe
{
    /**
     * @param string|null $description 描述信息
     */
    public function __construct(
        public ?string $description = null
    ) {}
}