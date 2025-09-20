<?php

declare(strict_types=1);

namespace Nova\Fibers\Attributes;

use Attribute;

/**
 * 退避策略属性
 * 
 * 用于定义重试时的退避策略，支持固定间隔、线性增长、指数退避等
 * 
 * @Annotation
 * @Target({"METHOD"})
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Backoff
{
    public const STRATEGY_FIXED = 'fixed';
    public const STRATEGY_LINEAR = 'linear';
    public const STRATEGY_EXPONENTIAL = 'exponential';
    
    /**
     * 构造函数
     * 
     * @param string $strategy 退避策略 (fixed, linear, exponential)
     * @param int $baseDelay 基础延迟（毫秒）
     * @param float $multiplier 倍数因子
     * @param int $maxDelay 最大延迟（毫秒）
     */
    public function __construct(
        public readonly string $strategy = self::STRATEGY_FIXED,
        public readonly int $baseDelay = 1000,
        public readonly float $multiplier = 2.0,
        public readonly int $maxDelay = 60000
    ) {
    }
}