<?php
declare(strict_types=1);

namespace Kode\Fibers\Event;

/**
 * 事件接口 - 定义事件的基本方法
 */
interface Event
{
    /**
     * 获取事件名称
     *
     * @return string
     */
    public function getName(): string;

    /**
     * 获取事件数据
     *
     * @return mixed
     */
    public function getData(): mixed;

    /**
     * 设置事件数据
     *
     * @param mixed $data 事件数据
     * @return self
     */
    public function setData(mixed $data): self;

    /**
     * 获取事件时间戳
     *
     * @return int
     */
    public function getTimestamp(): int;

    /**
     * 获取事件上下文数据
     *
     * @return array
     */
    public function getContext(): array;

    /**
     * 设置事件上下文数据
     *
     * @param array $context 上下文数据
     * @return self
     */
    public function setContext(array $context): self;

    /**
     * 获取指定的上下文数据
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function getContextValue(string $key, mixed $default = null): mixed;

    /**
     * 设置指定的上下文数据
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return self
     */
    public function setContextValue(string $key, mixed $value): self;

    /**
     * 停止事件传播
     *
     * @return void
     */
    public function stopPropagation(): void;

    /**
     * 检查事件传播是否已停止
     *
     * @return bool
     */
    public function isPropagationStopped(): bool;

    /**
     * 检查是否包含特定的上下文键
     *
     * @param string $key 键名
     * @return bool
     */
    public function hasContextKey(string $key): bool;

    /**
     * 获取事件的详细信息
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * 转换为JSON字符串
     *
     * @return string
     */
    public function toJson(): string;
}