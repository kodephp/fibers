<?php

declare(strict_types=1);

namespace Kode\Fibers\Context;

use Fiber;

/**
 * Fiber-specific context management
 *
 * This class provides fiber-aware context management,
 * allowing you to store and retrieve context data that is scoped to the current fiber.
 */
class Context
{
    /**
     * 存储每个纤程的上下文实例
     *
     * @var array
     */
    protected static array $contexts = [];

    /**
     * 上下文数据
     *
     * @var array
     */
    protected array $data = [];

    /**
     * 获取当前纤程的上下文
     *
     * @return static
     */
    public static function current(): static
    {
        $fiber = Fiber::getCurrent();
        $id = $fiber ? spl_object_id($fiber) : 'main';
        
        if (!isset(static::$contexts[$id])) {
            static::$contexts[$id] = new static();
        }
        
        return static::$contexts[$id];
    }

    /**
     * 设置上下文数据（支持链式调用）
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return static
     */
    public static function set(string $key, mixed $value): static
    {
        $context = static::current();
        $context->data[$key] = $value;
        return $context;
    }

    /**
     * 获取上下文数据
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $context = static::current();
        return $context->data[$key] ?? $default;
    }

    /**
     * 检查上下文数据是否存在
     *
     * @param string $key 键名
     * @return bool
     */
    public static function has(string $key): bool
    {
        $context = static::current();
        return isset($context->data[$key]);
    }

    /**
     * 删除上下文数据
     *
     * @param string $key 键名
     * @return bool 是否成功删除
     */
    public static function delete(string $key): bool
    {
        $context = static::current();
        if (isset($context->data[$key])) {
            unset($context->data[$key]);
            return true;
        }
        return false;
    }

    /**
     * 批量设置上下文数据
     *
     * @param array $data 数据数组
     * @return void
     */
    public static function setMultiple(array $data): void
    {
        $context = static::current();
        foreach ($data as $key => $value) {
            $context->data[$key] = $value;
        }
    }

    /**
     * 获取所有上下文数据
     *
     * @return array
     */
    public static function getAll(): array
    {
        return static::current()->data;
    }

    /**
     * 清空当前纤程的上下文数据（支持链式调用）
     *
     * @return static
     */
    public static function clear(): static
    {
        $fiber = Fiber::getCurrent();
        $id = $fiber ? spl_object_id($fiber) : 'main';
        
        if (isset(static::$contexts[$id])) {
            static::$contexts[$id]->data = [];
        }
        return static::current();
    }

    /**
     * 继承父纤程的上下文数据
     *
     * @return void
     */
    public static function inherit(): void
    {
        $currentFiber = Fiber::getCurrent();
        if (!$currentFiber) {
            return; // 在主线程中，没有父纤程
        }
        
        // 获取调用堆栈，找到父纤程
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $parentFiberId = null;
        
        // 查找可能的父纤程
        foreach ($backtrace as $frame) {
            if (isset($frame['object']) && $frame['object'] instanceof Fiber) {
                $parentFiberId = spl_object_id($frame['object']);
                break;
            }
        }
        
        // 如果找到父纤程，继承其上下文
        if ($parentFiberId && isset(static::$contexts[$parentFiberId])) {
            $currentId = spl_object_id($currentFiber);
            static::$contexts[$currentId] = clone static::$contexts[$parentFiberId];
        }
    }

    /**
     * 导出上下文数据为可序列化的数组
     *
     * @return array
     */
    public static function export(): array
    {
        return static::getAll();
    }

    /**
     * 从数组导入上下文数据
     *
     * @param array $data 序列化的上下文数据
     * @return void
     */
    public static function import(array $data): void
    {
        static::setMultiple($data);
    }

    public static function snapshot(): array
    {
        return static::export();
    }

    public static function restore(array $snapshot): void
    {
        static::clear();
        static::import($snapshot);
    }

    public static function runWith(array $context, callable $task): mixed
    {
        $snapshot = static::snapshot();
        static::setMultiple($context);

        try {
            return $task();
        } finally {
            static::restore($snapshot);
        }
    }

    public static function fork(array $extra = []): array
    {
        return array_merge(static::snapshot(), $extra);
    }
}
