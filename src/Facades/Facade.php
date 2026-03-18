<?php

namespace Kode\Fibers\Facades;

/**
 * Base Facade class
 *
 * 自动降级机制：
 * - 已安装 kode/facade：使用完整 Facade 功能
 * - 未安装：使用基础静态代理实现
 */
abstract class Facade
{
    protected static array $resolvedInstances = [];
    protected static bool $useNativeDriver = false;

    public static function __static(): void
    {
        static::$useNativeDriver = !class_exists(\Kode\Facade\Facade::class);
    }

    public static function isUsingNativeDriver(): bool
    {
        return static::$useNativeDriver;
    }

    protected static function getFacadeAccessor(): string
    {
        return '';
    }

    protected static function resolveFacadeInstance(string $name): mixed
    {
        if (isset(static::$resolvedInstances[$name])) {
            return static::$resolvedInstances[$name];
        }

        return static::$resolvedInstances[$name] = static::createInstance($name);
    }

    protected static function createInstance(string $name): mixed
    {
        $classMap = [
            'fibers' => \Kode\Fibers\Fibers::class,
            'pool' => \Kode\Fibers\Core\FiberPool::class,
            'channel' => \Kode\Fibers\Channel\Channel::class,
            'profiler' => \Kode\Fibers\Profiler\FiberProfiler::class,
        ];

        $class = $classMap[$name] ?? $name;
        
        if (class_exists($class)) {
            return new $class();
        }

        return null;
    }

    public static function swap(mixed $instance): void
    {
        static::$resolvedInstances[static::getFacadeAccessor()] = $instance;
    }

    public static function clearResolvedInstance(string $name): void
    {
        unset(static::$resolvedInstances[$name]);
    }

    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstances = [];
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::resolveFacadeInstance(static::getFacadeAccessor());
        
        if (!$instance) {
            throw new \RuntimeException('Facade accessor not found: ' . static::getFacadeAccessor());
        }

        return $instance->{$method}(...$args);
    }
}

Facade::__static();
