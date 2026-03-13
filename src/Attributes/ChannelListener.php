<?php

declare(strict_types=1);

namespace Kode\Fibers\Attributes;

use Attribute;

/**
 * ChannelListener 属性 - 自动注册通道监听器
 *
 * 此属性标记一个方法为通道监听器，当类被实例化时，
 * 该方法会自动注册为指定通道的监听器。
 *
 * @example
 * ```php
 * class OrderHandler
 * {
 *     #[ChannelListener('order.created')]
 *     public function onOrderCreated(array $data): void
 *     {
 *         // 处理订单创建事件
 *     }
 *
 *     #[ChannelListener('order.updated')]
 *     public function onOrderUpdated(array $data): void
 *     {
 *         // 处理订单更新事件
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ChannelListener implements Attribute
{
    /**
     * 通道名称
     *
     * @var string
     */
    protected string $channel;

    /**
     * 监听器优先级
     *
     * @var int
     */
    protected int $priority;

    /**
     * 是否只监听一次
     *
     * @var bool
     */
    protected bool $once;

    /**
     * 构造函数
     *
     * @param string $channel 通道名称
     * @param int $priority 监听器优先级（值越大优先级越高）
     * @param bool $once 是否只监听一次
     */
    public function __construct(string $channel, int $priority = 0, bool $once = false)
    {
        $this->channel = $channel;
        $this->priority = $priority;
        $this->once = $once;
    }

    /**
     * 获取通道名称
     *
     * @return string
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * 获取优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * 是否只监听一次
     *
     * @return bool
     */
    public function isOnce(): bool
    {
        return $this->once;
    }

    /**
     * 获取属性元数据
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return [
            'type' => 'channel_listener',
            'channel' => $this->channel,
            'priority' => $this->priority,
            'once' => $this->once,
        ];
    }
}
