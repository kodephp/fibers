<?php

declare(strict_types=1);

namespace Kode\Fibers;

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Channel\Channel;
use Kode\Context\Context;
use Kode\Fibers\Core\CircuitBreaker;
use Kode\Fibers\Core\RoundRobinBalancer;
use Kode\Fibers\Core\DistributedScheduler;
use Kode\Fibers\Core\InMemoryNodeTransport;
use Kode\Fibers\Contracts\NodeTransportInterface;
use Kode\Fibers\Profiler\FiberProfiler;
use Kode\Fibers\Profiler\ProfilerDashboard;
use Kode\Fibers\ORM\EloquentAdapter;
use Kode\Fibers\ORM\FixturesAdapter;
use Kode\Fibers\Support\Environment;
use Kode\Fibers\Support\CpuInfo;
use Kode\Fibers\Support\Roadmap;
use Kode\Fibers\Support\RuntimeBridge;
use Kode\Fibers\Support\HotReloader;
use Kode\Fibers\WebUI\WebUI;
use Kode\Fibers\Exceptions\FiberException;
use Kode\Fibers\Task\TaskRunner;
use Kode\Fibers\Task\Task;

/**
 * Fiber主类 - 提供便捷的协程操作接口
 *
 * @method static mixed run(callable $task, float $timeout = null)
 * @method static FiberPool pool(array $options = [])
 * @method static Channel channel(string $name, int $buffer = 0)
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
 * @method static array scheduleDistributedRemote(array $tasks, array $nodes = [], ?NodeTransportInterface $transport = null)
 * @method static array runtimeBridgeInfo()
 * @method static mixed runOnBridge(callable $task, string $preferred = null)
 * @method static array profile(callable $task, string $name = 'task')
 * @method static string profilerDashboard(array $records)
 * @method static EloquentAdapter eloquent(object $connection)
 * @method static FixturesAdapter fixtures(array $fixtures = [])
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
class Fibers
{
    /**
     * 是否启用安全析构模式
     *
     * @var bool
     */
    protected static bool $safeDestructMode = false;

    /**
     * 延迟执行的析构任务队列
     *
     * @var array
     */
    protected static array $deferredDestructTasks = [];

    /**
     * 当前应用的上下文数据
     *
     * @var array
     */
    protected static array $appContext = [];

    /**
     * 运行一个协程任务
     *
     * @param callable $task 任务回调
     * @param float|null $timeout 超时时间（秒）
     * @return mixed
     * @throws FiberException
     */
    public static function run(callable $task, ?float $timeout = null): mixed
    {
        // 检查环境
        Environment::check();
        
        // 自动启用安全析构模式（针对PHP < 8.4）
        if (PHP_VERSION_ID < 80400 && !static::$safeDestructMode) {
            static::enableSafeDestructMode();
        }
        
        try {
            // 创建任务对象
            $taskObj = Task::make($task, ['timeout' => $timeout]);
            
            // 创建临时纤程池执行任务
            $pool = new FiberPool(['size' => 1]);
            $result = $pool->run($taskObj);
            
            // 执行延迟的析构任务（如果有的话）
            static::processDeferredDestructTasks();
            
            return $result;
        } catch (\Throwable $e) {
            throw new FiberException('Fiber execution failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * 创建纤程池
     *
     * @param array $options 池配置选项
     * @return FiberPool
     */
    public static function pool(array $options = []): FiberPool
    {
        // 自动启用安全析构模式（针对PHP < 8.4）
        if (PHP_VERSION_ID < 80400 && !static::$safeDestructMode) {
            static::enableSafeDestructMode();
        }
        
        // 设置默认池大小为CPU核心数的4倍
        if (!isset($options['size'])) {
            $options['size'] = max(4, CpuInfo::get() * 4);
        }
        
        return new FiberPool($options);
    }

    /**
     * 创建通道
     *
     * @param string $name 通道名称
     * @param int $buffer 缓冲区大小
     * @return Channel
     */
    public static function channel(string $name, int $buffer = 0): Channel
    {
        return Channel::make($name, $buffer);
    }

    /**
     * 获取CPU核心数
     *
     * @return int
     */
    public static function cpuCount(): int
    {
        return CpuInfo::get();
    }

    /**
     * 启用安全析构模式（针对PHP < 8.4）
     *
     * @return void
     */
    public static function enableSafeDestructMode(): void
    {
        if (PHP_VERSION_ID < 80400 && !static::$safeDestructMode) {
            static::$safeDestructMode = true;
            
            // 注册一个shutdown函数来处理延迟的析构任务
            register_shutdown_function(function() {
                static::processDeferredDestructTasks();
            });
        }
    }

    /**
     * 延迟析构任务
     *
     * 用于PHP < 8.4版本中，在析构函数中无法安全调用Fiber::suspend()的情况
     *
     * @param callable $task 要延迟执行的任务
     * @return void
     */
    public static function deferDestructTask(callable $task): void
    {
        if (PHP_VERSION_ID >= 80400) {
            // PHP >= 8.4不需要延迟执行
            try {
                $task();
            } catch (\Throwable $e) {
                error_log('Error in destruct task: ' . $e->getMessage());
            }
            return;
        }

        if (static::$safeDestructMode) {
            static::$deferredDestructTasks[] = $task;
        }
    }

    /**
     * 处理延迟的析构任务
     *
     * @return void
     */
    protected static function processDeferredDestructTasks(): void
    {
        if (!empty(static::$deferredDestructTasks)) {
            $tasks = static::$deferredDestructTasks;
            static::$deferredDestructTasks = [];
            
            foreach ($tasks as $task) {
                try {
                    $task();
                } catch (\Throwable $e) {
                    // 忽略析构任务中的异常
                }
            }
        }
    }

    /**
     * 并发执行多个任务
     *
     * @param array $tasks 任务数组
     * @param float|null $timeout 超时时间（秒）
     * @return array 结果数组
     * @throws FiberException
     */
    public static function concurrent(array $tasks, ?float $timeout = null): array
    {
        try {
            return TaskRunner::concurrent($tasks, ['size' => count($tasks), 'timeout' => $timeout]);
        } catch (\Throwable $e) {
            throw new FiberException('Concurrent execution failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * 带重试机制的任务执行
     *
     * @param callable $task 任务回调
     * @param int $maxRetries 最大重试次数
     * @param float $retryDelay 重试延迟（秒）
     * @return mixed
     * @throws FiberException
     */
    public static function retry(callable $task, int $maxRetries = 3, float $retryDelay = 0.5): mixed
    {
        try {
            return TaskRunner::retry($task, $maxRetries, $retryDelay);
        } catch (\Throwable $e) {
            throw new FiberException('Retryable task failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * 安全的睡眠（在纤程中不会阻塞整个进程）
     *
     * @param float $seconds 睡眠秒数
     * @return void
     */
    public static function sleep(float $seconds): void
    {
        // 在PHP Fiber中，sleep会阻塞当前纤程，但不会阻塞整个进程
        usleep((int)($seconds * 1000000));
    }

    /**
     * 带超时的任务执行
     *
     * @param callable $task 任务回调
     * @param float $timeout 超时时间（秒）
     * @return mixed
     * @throws FiberException
     */
    public static function withTimeout(callable $task, float $timeout): mixed
    {
        return static::run($task, $timeout);
    }

    public static function go(callable $task, ?float $timeout = null): mixed
    {
        return static::run($task, $timeout);
    }

    public static function withContext(array $context, callable $task, ?float $timeout = null): mixed
    {
        return static::run(fn() => Context::fork(function () use ($context, $task) {
            Context::merge($context);
            return $task();
        }), $timeout);
    }

    public static function concurrentWithContext(array $context, array $tasks, ?float $timeout = null): array
    {
        $wrapped = [];
        foreach ($tasks as $key => $task) {
            $wrapped[$key] = function () use ($context, $task) {
                return Context::fork(function () use ($context, $task) {
                    Context::merge($context);
                    if ($task instanceof \Closure || is_callable($task)) {
                        return $task();
                    }
                    return $task;
                });
            };
        }

        return static::concurrent($wrapped, $timeout);
    }

    public static function batch(array $items, callable $handler, ?int $concurrency = null, ?float $timeout = null): array
    {
        $maxConcurrency = max(1, CpuInfo::get() * 2);
        $concurrency = max(1, min($concurrency ?? $maxConcurrency, count($items) > 0 ? count($items) : 1));
        $results = [];

        foreach (array_chunk($items, $concurrency, true) as $chunk) {
            $tasks = [];
            foreach ($chunk as $key => $item) {
                $tasks[$key] = fn() => $handler($item, $key);
            }

            $chunkResults = static::concurrent($tasks, $timeout);
            foreach ($chunkResults as $key => $value) {
                if ($value instanceof \Throwable) {
                    throw new FiberException(
                        sprintf('Batch task failed at key [%s]: %s', (string) $key, $value->getMessage()),
                        (int) $value->getCode(),
                        $value
                    );
                }
                $results[$key] = $value;
            }
        }

        return $results;
    }

    public static function resilientBatch(array $items, callable $handler, array $options = []): array
    {
        $config = array_merge([
            'concurrency' => max(1, CpuInfo::get()),
            'timeout' => null,
            'fail_fast' => false,
            'max_retries' => 1,
            'failure_threshold' => 5,
            'recovery_timeout' => 3.0,
            'half_open_max_calls' => 1,
        ], $options);

        $breaker = new CircuitBreaker(
            (int) $config['failure_threshold'],
            (float) $config['recovery_timeout'],
            (int) $config['half_open_max_calls']
        );

        $balancer = new RoundRobinBalancer();
        $distributed = $balancer->distribute($items, (int) $config['concurrency']);

        $results = [];
        $errors = [];
        $skipped = [];

        foreach ($distributed as $bucket) {
            $tasks = [];
            foreach ($bucket as $key => $item) {
                $tasks[$key] = function () use ($key, $item, $handler, $breaker, $config, &$skipped) {
                    $maxRetries = max(0, (int) $config['max_retries']);
                    $attempt = 0;

                    while ($attempt <= $maxRetries) {
                        if (!$breaker->allowRequest()) {
                            $skipped[$key] = 'circuit_open';
                            return null;
                        }

                        try {
                            $result = $handler($item, $key, $attempt);
                            $breaker->recordSuccess();
                            return $result;
                        } catch (\Throwable $e) {
                            $breaker->recordFailure();
                            if ($attempt >= $maxRetries) {
                                throw $e;
                            }
                            $attempt++;
                        }
                    }

                    return null;
                };
            }

            $chunkResult = static::concurrent($tasks, $config['timeout']);
            foreach ($chunkResult as $key => $value) {
                if ($value instanceof \Throwable) {
                    $errors[$key] = $value;
                    if ($config['fail_fast']) {
                        throw new FiberException(
                            sprintf('Resilient batch failed at key [%s]: %s', (string) $key, $value->getMessage()),
                            (int) $value->getCode(),
                            $value
                        );
                    }
                    continue;
                }

                if (!array_key_exists($key, $skipped)) {
                    $results[$key] = $value;
                }
            }
        }

        return [
            'results' => $results,
            'errors' => $errors,
            'skipped' => $skipped,
            'metrics' => [
                'total' => count($items),
                'success' => count($results),
                'errors' => count($errors),
                'skipped' => count($skipped),
            ],
            'breaker' => $breaker->metrics(),
        ];
    }

    public static function scheduleDistributed(array $tasks, array $nodes = []): array
    {
        $scheduler = new DistributedScheduler();
        foreach ($nodes as $nodeId => $meta) {
            $scheduler->registerNode((string) $nodeId, is_array($meta) ? $meta : []);
        }

        return $scheduler->dispatch($tasks);
    }

    public static function scheduleDistributedAdvanced(array $tasks, array $nodes = [], array $options = []): array
    {
        $scheduler = new DistributedScheduler();
        foreach ($nodes as $nodeId => $meta) {
            $scheduler->registerNode((string) $nodeId, is_array($meta) ? $meta : []);
        }

        $unhealthyNodes = (array) ($options['unhealthy_nodes'] ?? []);
        foreach ($unhealthyNodes as $nodeId) {
            $scheduler->setNodeHealth((string) $nodeId, false);
        }

        return $scheduler->dispatch($tasks);
    }

    public static function scheduleDistributedRemote(
        array $tasks,
        array $nodes = [],
        ?NodeTransportInterface $transport = null
    ): array {
        $transport = $transport ?? new InMemoryNodeTransport();
        $distributed = static::scheduleDistributedAdvanced($tasks, $nodes);
        $receipts = [];

        foreach ($distributed['assignments'] as $nodeId => $nodeTasks) {
            $receipts[$nodeId] = $transport->send((string) $nodeId, $nodeTasks);
        }

        return [
            'dispatch' => $distributed,
            'receipts' => $receipts,
        ];
    }

    public static function resilientRun(callable $task, array $options = []): mixed
    {
        $config = array_merge([
            'max_retries' => 1,
            'failure_threshold' => 5,
            'recovery_timeout' => 3.0,
            'half_open_max_calls' => 1,
            'fallback' => null,
        ], $options);
        $fallback = is_callable($config['fallback']) ? $config['fallback'] : null;

        $breaker = new CircuitBreaker(
            (int) $config['failure_threshold'],
            (float) $config['recovery_timeout'],
            (int) $config['half_open_max_calls']
        );

        $maxRetries = max(0, (int) $config['max_retries']);
        $attempt = 0;
        $lastError = null;

        while ($attempt <= $maxRetries) {
            try {
                return $breaker->execute($task, $fallback);
            } catch (\Throwable $e) {
                $lastError = $e;
                if ($attempt >= $maxRetries) {
                    break;
                }
                $attempt++;
            }
        }

        if ($fallback !== null) {
            return $fallback();
        }

        throw new FiberException('Resilient run failed: ' . $lastError?->getMessage(), (int) ($lastError?->getCode() ?? 0), $lastError);
    }

    public static function runtimeBridgeInfo(): array
    {
        return RuntimeBridge::detect();
    }

    public static function runOnBridge(callable $task, ?string $preferred = null): mixed
    {
        return RuntimeBridge::run($task, $preferred);
    }

    public static function profile(callable $task, string $name = 'task'): array
    {
        $profiler = new FiberProfiler();
        $result = $profiler->profile($name, $task);

        return [
            'result' => $result,
            'records' => $profiler->records(),
        ];
    }

    public static function profilerDashboard(array $records): string
    {
        return ProfilerDashboard::renderHtml($records);
    }

    public static function eloquent(object $connection): EloquentAdapter
    {
        return new EloquentAdapter($connection);
    }

    public static function fixtures(array $fixtures = []): FixturesAdapter
    {
        return new FixturesAdapter($fixtures);
    }

    /**
     * 等待所有任务完成
     *
     * @param array $tasks 任务数组
     * @return void
     */
    public static function waitAll(array $tasks): void
    {
        static::concurrent($tasks);
    }

    /**
     * 并行执行任务并收集结果
     *
     * @param array $tasks 任务数组
     * @param callable|null $callback 结果处理回调
     * @return mixed
     * @throws FiberException
     */
    public static function parallel(array $tasks, ?callable $callback = null): mixed
    {
        $results = static::concurrent($tasks);
        
        if ($callback) {
            return $callback($results);
        }
        
        return $results;
    }

    /**
     * 设置应用上下文
     *
     * @param array $context 上下文数据
     * @return void
     */
    public static function setAppContext(array $context): void
    {
        static::$appContext = $context;
    }

    /**
     * 获取应用上下文
     *
     * @return array
     */
    public static function getAppContext(): array
    {
        return static::$appContext;
    }

    /**
     * 获取上下文中的特定值
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getAppContextValue(string $key, mixed $default = null): mixed
    {
        return static::$appContext[$key] ?? $default;
    }

    /**
     * 设置上下文中的特定值
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return void
     */
    public static function setAppContextValue(string $key, mixed $value): void
    {
        static::$appContext[$key] = $value;
    }

    /**
     * 诊断运行环境
     *
     * @return array 诊断结果
     */
    public static function diagnose(): array
    {
        return Environment::diagnose();
    }

    public static function runtimeFeatures(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_version_id' => PHP_VERSION_ID,
            'native_fiber' => class_exists(\Fiber::class),
            'safe_destruct_supported' => PHP_VERSION_ID >= 80400,
            'php85_or_newer' => PHP_VERSION_ID >= 80500,
        ];
    }

    public static function roadmap(): array
    {
        return Roadmap::items();
    }

    /**
     * 创建热重载器实例
     *
     * @param array $watchDirs 监控的目录
     * @param array $options 配置选项
     * @return HotReloader
     */
    public static function hotReload(array $watchDirs = [], array $options = []): HotReloader
    {
        return new HotReloader($watchDirs, $options);
    }

    /**
     * 创建 Web UI 实例
     *
     * @param array $options 配置选项
     * @return WebUI
     */
    public static function webUI(array $options = []): WebUI
    {
        return new WebUI($options);
    }

    /**
     * 魔术方法 - 处理静态调用
     *
     * @param string $name 方法名
     * @param array $arguments 参数
     * @return mixed
     * @throws \BadMethodCallException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        // 实现IDE自动完成所需的方法
        throw new \BadMethodCallException(sprintf(
            'Method %s::%s does not exist or is not properly implemented',
            static::class,
            $name
        ));
    }
}
