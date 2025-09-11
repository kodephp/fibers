<?php

declare(strict_types=1);

namespace Nova\Fibers\Attributes;

use Attribute;

/**
 * 标记方法可在纤程中安全调用的属性
 * 
 * 该属性用于标记那些可以在纤程环境中安全执行的方法，
 * 通常这些方法不会执行阻塞操作或已经适配了非阻塞实现。
 * 
 * @Annotation
 * @Target({"METHOD"})
 */
#[Attribute(Attribute::TARGET_METHOD)]
class FiberSafe
{
    /**
     * 构造函数
     * 
     * @param string|null $description 描述信息
     */
    public function __construct(
        public readonly ?string $description = null
    ) {
    }
}