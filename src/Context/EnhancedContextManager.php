<?php

declare(strict_types=1);

namespace Nova\Fibers\Context;

use Fiber;

/**
 * 增强的上下文管理器类
 * 
 * 提供全局上下文管理功能，支持上下文隔离、权限控制和子纤程继承
 */
class EnhancedContextManager
{
    /**
     * 上下文存储
     * 
     * @var array<string, Context>
     */
    private static array $contexts = [];

    /**
     * 当前上下文ID映射
     * 
     * @var array<string, string>
     */
    private static array $currentContextId = [];

    /**
     * 上下文隔离键
     * 
     * @var array<string, array<string>>
     */
    private static array $isolationKeys = [];

    /**
     * 上下文权限映射
     * 
     * @var array<string, array<string, bool>>
     */
    private static array $contextPermissions = [];

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

        // 检查是否被隔离
        $fiberId = self::getFiberId();
        if (isset(self::$isolationKeys[$fiberId]) && in_array($key, self::$isolationKeys[$fiberId], true)) {
            return $default;
        }

        return $context->value($key, $default);
    }

    /**
     * 设置上下文中的值
     * 
     * @param string $key 键
     * @param mixed $value 值
     * @param bool $checkPermission 是否检查权限
     * @return Context 新上下文
     * @throws \RuntimeException 如果没有权限
     */
    public static function withValue(string $key, mixed $value, bool $checkPermission = true): Context
    {
        $context = self::getCurrentContext();
        if ($context === null) {
            $context = new Context('root');
        }

        // 检查权限
        if ($checkPermission && !self::hasPermission($key)) {
            throw new \RuntimeException("Permission denied to set context key: {$key}");
        }

        $newContext = $context->withValue($key, $value);
        self::setCurrentContext($newContext);
        return $newContext;
    }

    /**
     * 创建带隔离键的新上下文
     * 
     * @param array $isolationKeys 需要隔离的键
     * @return Context 新上下文
     */
    public static function withIsolation(array $isolationKeys): Context
    {
        $fiberId = self::getFiberId();
        self::$isolationKeys[$fiberId] = $isolationKeys;
        
        $context = self::getCurrentContext();
        if ($context === null) {
            $context = new Context('root');
        }

        // 创建一个新的上下文，但标记某些键为隔离
        $newContext = new Context(uniqid('isolated_'));
        foreach ($context->all() as $key => $value) {
            if (!in_array($key, $isolationKeys, true)) {
                $newContext = $newContext->withValue($key, $value);
            }
        }
        
        self::setCurrentContext($newContext);
        return $newContext;
    }

    /**
     * 设置上下文权限
     * 
     * @param string $key 键
     * @param bool $allowed 是否允许访问
     * @return void
     */
    public static function setPermission(string $key, bool $allowed): void
    {
        $fiberId = self::getFiberId();
        if (!isset(self::$contextPermissions[$fiberId])) {
            self::$contextPermissions[$fiberId] = [];
        }
        
        self::$contextPermissions[$fiberId][$key] = $allowed;
    }

    /**
     * 检查是否有权限访问指定键
     * 
     * @param string $key 键
     * @return bool 是否有权限
     */
    public static function hasPermission(string $key): bool
    {
        $fiberId = self::getFiberId();
        if (!isset(self::$contextPermissions[$fiberId][$key])) {
            // 默认允许访问
            return true;
        }
        
        return self::$contextPermissions[$fiberId][$key];
    }

    /**
     * 继承父纤程上下文（子纤程使用）
     * 
     * @param string $parentFiberId 父纤程ID
     * @return void
     */
    public static function inheritFromParent(string $parentFiberId): void
    {
        $childFiberId = self::getFiberId();
        
        // 继承父纤程的上下文
        if (isset(self::$currentContextId[$parentFiberId])) {
            $contextId = self::$currentContextId[$parentFiberId];
            if (isset(self::$contexts[$contextId])) {
                $parentContext = self::$contexts[$contextId];
                // 创建新的上下文实例，避免引用问题
                $childContext = new Context(uniqid('inherited_'));
                foreach ($parentContext->all() as $key => $value) {
                    $childContext = $childContext->withValue($key, $value);
                }
                self::setCurrentContext($childContext);
            }
        }
        
        // 继承父纤程的权限设置
        if (isset(self::$contextPermissions[$parentFiberId])) {
            self::$contextPermissions[$childFiberId] = self::$contextPermissions[$parentFiberId];
        }
        
        // 继承父纤程的隔离键设置
        if (isset(self::$isolationKeys[$parentFiberId])) {
            self::$isolationKeys[$childFiberId] = self::$isolationKeys[$parentFiberId];
        }
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
        
        // 清除相关设置
        unset(self::$isolationKeys[$fiberId]);
        unset(self::$contextPermissions[$fiberId]);
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