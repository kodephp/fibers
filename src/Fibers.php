<?php

declare(strict_types=1);

namespace Kode\Fibers;

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Channel\Channel;
use Kode\Context\Context;
use Kode\Fibers\Concurrency\CancellationToken;
use Kode\Fibers\Concurrency\CancellationTokenSource;
use Kode\Fibers\Concurrency\CancelledException;
use Kode\Fibers\Concurrency\Coroutine;
use Kode\Fibers\Concurrency\FiberLocal;
use Kode\Fibers\Concurrency\Mutex;
use Kode\Fibers\Concurrency\Runtime;
use Kode\Fibers\Concurrency\Scheduler;
use Kode\Fibers\Concurrency\Semaphore;
use Kode\Fibers\Concurrency\WaitGroup;
use Kode\Fibers\Core\CircuitBreaker;
use Kode\Fibers\Core\RoundRobinBalancer;
use Kode\Fibers\Core\DistributedScheduler;
use Kode\Fibers\Core\InMemoryNodeTransport;
use Kode\Fibers\Contracts\NodeTransportInterface;
use Kode\Fibers\Profiler\FiberProfiler;
use Kode\Fibers\ORM\EloquentAdapter;
use Kode\Fibers\ORM\FixturesAdapter;
use Kode\Fibers\Support\Environment;
use Kode\Fibers\Support\CpuInfo;
use Kode\Fibers\Support\Roadmap;
use Kode\Fibers\Support\RuntimeBridge;
use Kode\Fibers\Support\HotReloader;
use Kode\Fibers\Support\Php85Features;
use Kode\Fibers\WebUI\WebUI;
use Kode\Fibers\Pool\ConnectionPool;
use Kode\Fibers\Debug\FiberDebugger;
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
 * @method static Scheduler scheduler()
 * @method static Coroutine async(callable $task, string $name = null)
 * @method static mixed await(Coroutine|array $coroutine, float $timeout = null)
 * @method static array loop(iterable $tasks)
 * @method static WaitGroup waitGroup(int $delta = 0)
 * @method static Semaphore semaphore(int $permits)
 * @method static Mutex mutex()
 * @method static FiberLocal local(\Closure $initializer = null)
 * @method static CancellationToken timeoutToken(float $seconds)
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
            return Runtime::execute($task, $timeout);
        } catch (CancelledException | FiberException $e) {
            // 取消 / 超时保留原始异常类型，便于调用方精确捕获
            throw $e;
        } catch (\Throwable $e) {
            throw new FiberException('Fiber execution failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        } finally {
            // 执行延迟的析构任务（如果有的话）
            static::processDeferredDestructTasks();
        }
    }

    /**
     * 获取当前调度器（协程内为所属调度器，否则为进程级默认调度器）
     *
     * @return Scheduler
     */
    public static function scheduler(): Scheduler
    {
        return Runtime::scheduler();
    }

    /**
     * 创建一个协程（不阻塞，返回协程句柄）
     *
     * 在事件循环外调用时会挂到默认调度器上，需配合 {@see static::loop()}
     * 或 {@see static::await()} 驱动执行。
     *
     * @param callable $task 协程主体
     * @param string|null $name 可选名称
     * @return Coroutine
     */
    public static function async(callable $task, ?string $name = null): Coroutine
    {
        return static::scheduler()->go($task, $name);
    }

    /**
     * 等待协程（或协程数组）完成并取回结果
     *
     * @param Coroutine|array $coroutine
     * @param float|null $timeout
     * @return mixed
     * @throws \Throwable
     */
    public static function await(Coroutine|array $coroutine, ?float $timeout = null): mixed
    {
        return static::scheduler()->await($coroutine, $timeout);
    }

    /**
     * 在一个全新的事件循环中执行一批任务并返回结果
     *
     * @param iterable $tasks
     * @return array
     * @throws \Throwable
     */
    public static function loop(iterable $tasks): array
    {
        return (new Scheduler())->runAll($tasks);
    }

    /**
     * 创建等待组
     *
     * @param int $delta 初始计数
     * @return WaitGroup
     */
    public static function waitGroup(int $delta = 0): WaitGroup
    {
        $group = new WaitGroup();

        if ($delta > 0) {
            $group->add($delta);
        }

        return $group;
    }

    /**
     * 创建信号量（并发限流）
     *
     * @param int $permits 并发上限
     * @return Semaphore
     */
    public static function semaphore(int $permits): Semaphore
    {
        return new Semaphore($permits);
    }

    /**
     * 创建协程互斥锁
     *
     * @return Mutex
     */
    public static function mutex(): Mutex
    {
        return new Mutex();
    }

    /**
     * 创建纤程本地变量
     *
     * @param \Closure|null $initializer 首次访问时生成默认值的初始化器
     * @return FiberLocal
     */
    public static function local(?\Closure $initializer = null): FiberLocal
    {
        return new FiberLocal($initializer);
    }

    /**
     * 创建一个在指定秒数后自动取消的取消令牌
     *
     * @param float $seconds 秒数
     * @return CancellationToken
     */
    public static function timeoutToken(float $seconds): CancellationToken
    {
        return CancellationTokenSource::withTimeout($seconds)->token();
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
        if ($tasks === []) {
            return [];
        }

        try {
            // timeout 作用于每个任务：到期真正中断，不再被静默丢弃
            return TaskRunner::concurrent($tasks, ['timeout' => $timeout]);
        } catch (CancelledException | FiberException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new FiberException('Concurrent execution failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * 并发执行多个任务，任一失败即抛出（结果不含 Throwable）
     *
     * @param array $tasks 任务数组
     * @param float|null $timeout 单个任务的超时时间（秒）
     * @param int|null $concurrency 并发上限
     * @return array
     * @throws \Throwable
     */
    public static function concurrentAll(array $tasks, ?float $timeout = null, ?int $concurrency = null): array
    {
        if ($tasks === []) {
            return [];
        }

        return Runtime::all($tasks, $timeout, $concurrency);
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
        // 事件循环内让出执行权（其它协程可继续推进），循环外退化为 usleep
        Runtime::sleep($seconds);
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
        if ($items === []) {
            return [];
        }

        $maxConcurrency = max(1, CpuInfo::get() * 2);
        $concurrency = max(1, min($concurrency ?? $maxConcurrency, count($items)));

        $tasks = [];
        foreach ($items as $key => $item) {
            $tasks[$key] = static fn(): mixed => $handler($item, $key);
        }

        // 用信号量控制并发上限：空出的名额会被立刻复用，
        // 不再像分块串行那样被最慢的任务拖住整批。
        $results = Runtime::settleAll($tasks, $timeout, $concurrency);

        foreach ($results as $key => $value) {
            if ($value instanceof \Throwable) {
                throw new FiberException(
                    sprintf('Batch task failed at key [%s]: %s', (string) $key, $value->getMessage()),
                    (int) $value->getCode(),
                    $value
                );
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

    /**
     * 渲染性能分析仪表盘
     *
     * 接收由 {@see Fibers::profile()} 产生的记录数组，构建性能分析器后渲染可视化面板。
     * 此前该方法会忽略传入的 $records，此处修正为真正使用记录数据。
     *
     * @param array $records 性能记录（每条包含 name/status/duration_ms/memory_delta/error 等字段）
     * @return string HTML 内容
     */
    public static function profilerDashboard(array $records): string
    {
        $profiler = new FiberProfiler();
        // 直接载入调用方传入的实测记录，不再重新测量
        // （否则 duration_ms/status/error 会被覆盖为 ~0ms/成功，面板失真）
        $profiler->loadRecords($records);

        $webUI = new WebUI(['profiler' => $profiler]);
        return $webUI->renderDashboard();
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
     * 创建连接池实例
     *
     * @param array $config 配置选项
     * @param callable|null $factory 连接工厂
     * @return ConnectionPool
     */
    public static function connectionPool(array $config = [], ?callable $factory = null): ConnectionPool
    {
        return new ConnectionPool($config, $factory);
    }

    /**
     * 创建PDO连接池
     *
     * @param string $dsn 数据源名称
     * @param string $username 用户名
     * @param string $password 密码
     * @param array $options PDO选项
     * @param array $poolConfig 连接池配置
     * @return ConnectionPool
     */
    public static function pdoPool(string $dsn, string $username = '', string $password = '', array $options = [], array $poolConfig = []): ConnectionPool
    {
        return ConnectionPool::pdo($dsn, $username, $password, $options, $poolConfig);
    }

    /**
     * 创建Redis连接池
     *
     * @param string $host 主机
     * @param int $port 端口
     * @param string $password 密码
     * @param int $database 数据库
     * @param array $poolConfig 连接池配置
     * @return ConnectionPool
     */
    public static function redisPool(string $host = '127.0.0.1', int $port = 6379, string $password = '', int $database = 0, array $poolConfig = []): ConnectionPool
    {
        return ConnectionPool::redis($host, $port, $password, $database, $poolConfig);
    }

    /**
     * 获取调试器实例
     *
     * @return FiberDebugger
     */
    public static function debugger(): FiberDebugger
    {
        return new FiberDebugger();
    }

    /**
     * 启用调试模式
     *
     * @return void
     */
    public static function enableDebug(): void
    {
        FiberDebugger::enable();
    }

    /**
     * 禁用调试模式
     *
     * @return void
     */
    public static function disableDebug(): void
    {
        FiberDebugger::disable();
    }

    /**
     * 获取PHP 8.5特性支持状态
     *
     * @return array
     */
    public static function php85Features(): array
    {
        return Php85Features::getAvailableFeatures();
    }

    /**
     * 并发cURL请求
     *
     * @param array $urls URL列表
     * @param array $options 选项
     * @return array
     */
    public static function multiCurl(array $urls, array $options = []): array
    {
        return Php85Features::multiCurl($urls, $options);
    }

    /**
     * 跨机器执行任务（便捷方法）
     *
     * @param string $nodeId 节点ID
     * @param callable $task 任务
     * @return mixed
     */
    public static function remote(string $nodeId, callable $task, ?float $timeout = null): mixed
    {
        return static::onNode($nodeId, $task, [], $timeout);
    }

    /**
     * 在指定节点执行任务
     *
     * @param string $nodeId 节点ID
     * @param callable $task 任务
     * @param array $nodeConfig 节点配置
     * @return mixed
     */
    public static function onNode(string $nodeId, callable $task, array $nodeConfig = [], ?float $timeout = null): mixed
    {
        $nodes = [$nodeId => array_merge(['healthy' => true], $nodeConfig)];
        $dispatch = static::scheduleDistributed([$nodeId => $task], $nodes);

        // 节点不健康 / 标签不匹配时任务不会被分派，此时应显式失败而非静默返回 null
        if (!isset($dispatch['assignments'][$nodeId][$nodeId])) {
            throw new FiberException(sprintf('节点 [%s] 不可用，任务未被分派', $nodeId));
        }

        // 当前进程即为该节点的执行端，直接在协程中运行任务并返回真实结果
        return static::run($task, $timeout);
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
