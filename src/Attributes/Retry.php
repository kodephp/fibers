<?php

declare(strict_types=1);

namespace Nova\Fibers\Attributes;

use Attribute;

/**
 * 重试设置属性
 * 
 * 用于标记方法的重试策略，包括最大重试次数和重试间隔
 * 
 * @Annotation
 * @Target({"METHOD"})
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Retry
{
    /**
     * 构造函数
     * 
     * @param int $maxAttempts 最大重试次数
     * @param int $delay 重试间隔（毫秒）
     * @param float $multiplier 间隔倍数（指数退避）
     * @param array $exceptions 需要重试的异常类型
     */
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly int $delay = 1000,
        public readonly float $multiplier = 1.0,
        public readonly array $exceptions = []
    ) {
    }
}