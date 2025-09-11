<?php

declare(strict_types=1);

namespace Nova\Fibers\Context;

/**
 * Fiber上下文管理器
 *
 * 用于在Fiber之间传递和管理上下文
 */
class ContextManager
{
    /**
     * @var array<string, Context> 上下文存储
     */
    private static array $contexts = [];

    /**
     * @var string|null 当前上下文ID
     */
    private static ?string $currentContextId = null;

    /**
     * 设置当前上下文
     *
     * @param Context $context 上下文实例
     * @return void
     */
    public static function setCurrentContext(Context $context): void
    {
        self::$currentContextId = $context->getId();
        self::$contexts[self::$currentContextId] = $context;
    }

    /**
     * 获取当前上下文
     *
     * @return Context|null
     */
    public static function getCurrentContext(): ?Context
    {
        if (self::$currentContextId === null) {
            return null;
        }

        return self::$contexts[self::$currentContextId] ?? null;
    }

    /**
     * 获取上下文值
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
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
     * 设置上下文值
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return Context 新的上下文实例
     */
    public static function withValue(string $key, mixed $value): Context
    {
        $context = self::getCurrentContext();

        if ($context === null) {
            $context = new Context();
        }

        $newContext = $context->withValue($key, $value);
        self::setCurrentContext($newContext);

        return $newContext;
    }

    /**
     * 清除上下文
     *
     * @return void
     */
    public static function clear(): void
    {
        if (self::$currentContextId !== null) {
            unset(self::$contexts[self::$currentContextId]);
            self::$currentContextId = null;
        }
    }
}
