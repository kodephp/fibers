<?php

declare(strict_types=1);

namespace Nova\Fibers\Attributes;

use Attribute;

/**
 * 超时设置属性
 * 
 * 用于标记方法的执行超时时间，单位为秒。
 * 
 * @Annotation
 * @Target({"METHOD"})
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Timeout
{
    /**
     * 构造函数
     * 
     * @param int $seconds 超时时间（秒）
     */
    public function __construct(
        public readonly int $seconds
    ) {
    }
}