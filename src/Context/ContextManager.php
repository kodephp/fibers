<?php

declare(strict_types=1);

namespace Nova\Fibers\Context;

use Fiber;

/**
 * 上下文管理器类
 * 
 * 提供全局上下文管理功能，用于在纤程间存储和获取上下文
 */
class ContextManager
{
    /**
     * 上下文存储
     * 
     * @var array<string, Context>
     */
    private static array $contexts = [];

    /**
     * 当前上下文ID
     * 
     * @var array<string, string>
     */
    private static array $currentContextId = [];

    /**
     * 设置当前上下文
     * 
     * @param Context $context 上下文
     * @return void
     */
    public static function setCurrentContext(Context $context): void
    {
        $fiberId = self::getFiberId();
        self::$contexts[$context->getId()] = $context;
        self::$currentContextId[$fiberId] = $context->getId();
    }

    /**
     * 获取当前上下文
     * 
     * @return Context|null 当前上下文
     */
    public static function getCurrentContext(): ?Context
    {
        $fiberId = self::getFiberId();
        if (!isset(self::$currentContextId[$fiberId])) {
            return null;
        }

        $contextId = self::$currentContextId[$fiberId];
        if (!isset(self::$contexts[$contextId])) {
            return null;
        }

        return self::$contexts[$contextId];
    }

    /**
     * 获取上下文中的值
     * 
     * @param string $key 键
     * @param mixed $default 默认值
     * @return mixed 值
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $context = self::getCurrentContext();
        if ($context === null) {
            return $default;
        }

        return $context->value($key, $default);
    }

    /**
     * 设置上下文中的值
     * 
     * @param string $key 键
     * @param mixed $value 值
     * @return Context 新上下文
     */
    public static function withValue(string $key, mixed $value): Context
    {
        $context = self::getCurrentContext();
        if ($context === null) {
            $context = new Context('root');
        }

        $newContext = $context->withValue($key, $value);
        self::setCurrentContext($newContext);
        return $newContext;
    }

    /**
     * 清除当前上下文
     * 
     * @return void
     */
    public static function clear(): void
    {
        $fiberId = self::getFiberId();
        if (isset(self::$currentContextId[$fiberId])) {
            $contextId = self::$currentContextId[$fiberId];
            unset(self::$contexts[$contextId]);
            unset(self::$currentContextId[$fiberId]);
        }
    }

    /**
     * 获取当前纤程ID
     * 
     * @return string 纤程ID
     */
    private static function getFiberId(): string
    {
        $fiber = Fiber::getCurrent();
        if ($fiber === null) {
            return 'main';
        }

        return spl_object_hash($fiber);
    }
}
