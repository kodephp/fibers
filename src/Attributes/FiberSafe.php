<?php

declare(strict_types=1);

namespace Kode\Fibers\Attributes;

use Attribute;

/**
 * FiberSafe 属性 - 标记方法可在纤程中安全调用
 *
 * 此属性指示一个方法已经过测试，可以在纤程上下文中安全调用，
 * 并且能够正确处理纤程的挂起和恢复操作。
 *
 * @example
 * ```php
 * #[FiberSafe]
 * class ApiService
 * {
 *     public function fetchData(): array
 *     {
 *         // 此方法可在纤程中安全调用
 *         return [];
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::TARGET_FUNCTION)]
class FiberSafe implements Attribute
{
    /**
     * 是否启用严格模式
     *
     * @var bool
     */
    protected bool $strictMode;

    /**
     * 构造函数
     *
     * @param bool $strictMode 是否启用严格模式（默认为 true）
     */
    public function __construct(bool $strictMode = true)
    {
        $this->strictMode = $strictMode;
    }

    /**
     * 获取严格模式设置
     *
     * @return bool
     */
    public function isStrictMode(): bool
    {
        return $this->strictMode;
    }

    /**
     * 获取属性元数据
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return [
            'type' => 'fiber_safe',
            'strict_mode' => $this->strictMode,
        ];
    }
}
