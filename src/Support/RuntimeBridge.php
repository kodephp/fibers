<?php

declare(strict_types=1);

namespace Kode\Fibers\Support;

class RuntimeBridge
{
    public static function detect(): array
    {
        return [
            'swoole' => extension_loaded('swoole') || extension_loaded('openswoole'),
            'openswoole' => extension_loaded('openswoole'),
            'swow' => extension_loaded('swow') || class_exists('Swow\\Coroutine'),
            'workerman' => class_exists('Workerman\\Worker'),
            'native_fiber' => class_exists(\Fiber::class),
        ];
    }

    public static function bestAvailable(): string
    {
        $detected = static::detect();
        if ($detected['openswoole']) {
            return 'openswoole';
        }
        if ($detected['swoole']) {
            return 'swoole';
        }
        if ($detected['swow']) {
            return 'swow';
        }
        if ($detected['workerman']) {
            return 'workerman';
        }
        return 'native';
    }

    public static function run(callable $task, ?string $preferred = null): mixed
    {
        $runtime = $preferred ?: static::bestAvailable();
        if (!in_array($runtime, ['native', 'swoole', 'openswoole', 'swow', 'workerman'], true)) {
            $runtime = 'native';
        }

        return $task();
    }
}
