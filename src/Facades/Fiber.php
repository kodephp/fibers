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
 * @method static mixed go(callable $task, float $timeout = null)
 * @method static mixed withContext(array $context, callable $task, float $timeout = null)
 * @method static array concurrentWithContext(array $context, array $tasks, float $timeout = null)
 * @method static array batch(array $items, callable $handler, int $concurrency = null, float $timeout = null)
 * @method static array resilientBatch(array $items, callable $handler, array $options = [])
 * @method static mixed resilientRun(callable $task, array $options = [])
 * @method static array scheduleDistributed(array $tasks, array $nodes = [])
 * @method static array scheduleDistributedAdvanced(array $tasks, array $nodes = [], array $options = [])
 * @method static array scheduleDistributedRemote(array $tasks, array $nodes = [], ?\Kode\Fibers\Contracts\NodeTransportInterface $transport = null)
 * @method static array runtimeBridgeInfo()
 * @method static mixed runOnBridge(callable $task, string $preferred = null)
 * @method static array profile(callable $task, string $name = 'task')
 * @method static string profilerDashboard(array $records)
 * @method static \Kode\Fibers\ORM\EloquentAdapter eloquent(object $connection)
 * @method static \Kode\Fibers\ORM\FixturesAdapter fixtures(array $fixtures = [])
 * @method static void waitAll(array $tasks)
 * @method static mixed parallel(array $tasks, callable $callback = null)
 * @method static array runtimeFeatures()
 * @method static array roadmap()
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
    protected static function id(): string
    {
        return 'fiber';
    }
}
