<?php
declare(strict_types=1);

namespace Kode\Fibers\Facades;

/**
 * Fiber 门面类 - 提供对Fiber相关功能的静态访问
 *
 * @method static mixed run(callable $task, float $timeout = null)
 * @method static \Kode\Fibers\Core\FiberPool pool(array $options = [])
 * @method static \Kode\Fibers\Channel\Channel channel(string $name, int $buffer = 0)
 * @method static int cpuCount()
 * @method static array concurrent(array $tasks, float $timeout = null)
 * @method static mixed retry(callable $task, int $maxRetries = 3, float $retryDelay = 0.5)
 * @method static void sleep(float $seconds)
 * @method static mixed withTimeout(callable $task, float $timeout)
 * @method static void waitAll(array $tasks)
 * @method static mixed parallel(array $tasks, callable $callback = null)
 * @method static void setAppContext(array $context)
 * @method static array getAppContext()
 * @method static mixed getAppContextValue(string $key, mixed $default = null)
 * @method static void setAppContextValue(string $key, mixed $value)
 * @method static array diagnose()
 * @method static void enableSafeDestructMode()
 * @method static void deferDestructTask(callable $task)
 */
class Fiber extends Facade
{
    /**
     * 获取组件的注册名称
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'fiber';
    }
}