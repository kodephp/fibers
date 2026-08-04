<?php

declare(strict_types=1);

namespace Kode\Fibers\Support;

use Kode\Fibers\Concurrency\Runtime;
use Kode\Fibers\Exceptions\FiberException;

/**
 * 运行时桥接
 *
 * 探测当前进程可用的协程运行时，并在其上执行任务。
 *
 * v4 起 `preferred` 参数真正生效：
 * - 指定了不受支持的运行时会抛出异常，而不是被静默忽略；
 * - 传 `auto`（或 null）时按优先级自动选择；
 * - 无论选中哪种运行时，都会通过对应实现执行任务，
 *   `native` 走本库的 {@see Runtime} 事件循环。
 */
class RuntimeBridge
{
    /**
     * 支持的运行时标识
     */
    public const RUNTIMES = ['native', 'swoole', 'openswoole', 'swow', 'workerman'];

    /**
     * 最近一次实际选中的运行时
     */
    protected static string $lastRuntime = 'native';

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

    /**
     * 指定运行时当前是否可用
     */
    public static function supports(string $runtime): bool
    {
        $detected = static::detect();

        return match ($runtime) {
            'native' => (bool) $detected['native_fiber'],
            'swoole' => (bool) $detected['swoole'],
            'openswoole' => (bool) $detected['openswoole'],
            'swow' => (bool) $detected['swow'],
            'workerman' => (bool) $detected['workerman'],
            default => false,
        };
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

    /**
     * 解析最终使用的运行时
     *
     * @throws FiberException 指定了未知或不可用的运行时
     */
    public static function resolve(?string $preferred = null): string
    {
        if ($preferred === null || $preferred === '' || $preferred === 'auto') {
            return static::bestAvailable();
        }

        $preferred = strtolower($preferred);

        if (!in_array($preferred, self::RUNTIMES, true)) {
            throw new FiberException(sprintf(
                '未知的运行时 [%s]，可选值：%s',
                $preferred,
                implode(', ', self::RUNTIMES)
            ));
        }

        if (!static::supports($preferred)) {
            throw new FiberException(sprintf(
                '运行时 [%s] 当前不可用，请安装对应扩展或改用 auto 自动选择',
                $preferred
            ));
        }

        return $preferred;
    }

    /**
     * 最近一次实际选中的运行时
     */
    public static function lastRuntime(): string
    {
        return static::$lastRuntime;
    }

    /**
     * 在选定的运行时上执行任务
     *
     * @throws FiberException
     * @throws \Throwable 任务自身抛出的异常
     */
    public static function run(callable $task, ?string $preferred = null): mixed
    {
        $runtime = static::resolve($preferred);
        static::$lastRuntime = $runtime;

        return match ($runtime) {
            'swoole', 'openswoole' => static::runOnSwoole($task),
            'swow' => static::runOnSwow($task),
            default => Runtime::execute($task),
        };
    }

    /**
     * 在 Swoole / OpenSwoole 协程中执行
     */
    protected static function runOnSwoole(callable $task): mixed
    {
        // 已处于 Swoole 协程内则直接执行
        if (class_exists('\\Swoole\\Coroutine') && \Swoole\Coroutine::getCid() > 0) {
            return $task();
        }

        if (!function_exists('\\Swoole\\Coroutine\\run')) {
            return Runtime::execute($task);
        }

        $result = null;
        $error = null;

        \Swoole\Coroutine\run(static function () use ($task, &$result, &$error): void {
            try {
                $result = $task();
            } catch (\Throwable $e) {
                $error = $e;
            }
        });

        if ($error !== null) {
            throw $error;
        }

        return $result;
    }

    /**
     * 在 Swow 协程中执行
     */
    protected static function runOnSwow(callable $task): mixed
    {
        if (!class_exists('\\Swow\\Coroutine')) {
            return Runtime::execute($task);
        }

        $result = null;
        $error = null;

        $coroutine = \Swow\Coroutine::run(static function () use ($task, &$result, &$error): void {
            try {
                $result = $task();
            } catch (\Throwable $e) {
                $error = $e;
            }
        });

        if (method_exists($coroutine, 'join')) {
            $coroutine->join();
        }

        if ($error !== null) {
            throw $error;
        }

        return $result;
    }
}
