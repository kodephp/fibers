<?php

declare(strict_types=1);

namespace Kode\Fibers\Attributes;

use Attribute;

/**
 * Timeout 属性 - 设置纤程方法执行超时时间
 *
 * 此属性为方法指定执行超时时间（秒），当方法在纤程中执行时，
 * 如果超过指定时间，将抛出超时异常。
 *
 * @example
 * ```php
 * class ApiService
 * {
 *     #[Timeout(10)]
 *     public function fetchUser(int $id): array
 *     {
 *         // 此方法最多执行10秒
 *         return json_decode(file_get_contents("https://api.com/users/$id"), true);
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Timeout implements Attribute
{
    /**
     * 超时时间（秒）
     *
     * @var float
     */
    protected float $seconds;

    /**
     * 构造函数
     *
     * @param float $seconds 超时时间（秒）
     */
    public function __construct(float $seconds)
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('超时时间必须大于0秒');
        }
        $this->seconds = $seconds;
    }

    /**
     * 获取超时时间
     *
     * @return float
     */
    public function getSeconds(): float
    {
        return $this->seconds;
    }

    /**
     * 获取超时时间（毫秒）
     *
     * @return int
     */
    public function getMilliseconds(): int
    {
        return (int)($this->seconds * 1000);
    }

    /**
     * 获取属性元数据
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return [
            'type' => 'timeout',
            'seconds' => $this->seconds,
            'milliseconds' => $this->getMilliseconds(),
        ];
    }
}
