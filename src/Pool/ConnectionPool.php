<?php

declare(strict_types=1);

namespace Kode\Fibers\Pool;

use Kode\Fibers\Concurrency\Scheduler;
use Kode\Fibers\Concurrency\Suspension;
use Kode\Fibers\Exceptions\FiberException;

/**
 * 通用连接池
 *
 * 提供可复用的连接管理，支持数据库、Redis、HTTP等连接类型。
 *
 * 设计要点：
 * - 惰性初始化：构造函数不创建任何连接，首次借出时才按 min_connections 预热，
 *   因此 `new ConnectionPool()` 后再 `setFactory()` 的用法是合法的。
 * - 协程感知等待：池满时若处于协程上下文则挂起让权（不阻塞事件循环），
 *   否则退化为带超时的阻塞轮询。
 * - 空闲回收：超过 idle_timeout 的空闲连接会在借出/回收时被销毁。
 */
class ConnectionPool implements ConnectionPoolInterface
{
    /**
     * 连接池配置
     */
    protected array $config;

    /**
     * 连接工厂回调
     *
     * @var callable|null
     */
    protected $factory;

    /**
     * 验证回调
     *
     * @var callable|null
     */
    protected $validator;

    /**
     * 销毁回调
     *
     * @var callable|null
     */
    protected $destroyer;

    /**
     * 空闲连接队列（元素为 ['connection' => mixed, 'idle_since' => float]）
     */
    protected array $idleConnections = [];

    /**
     * 活跃连接映射
     */
    protected array $activeConnections = [];

    /**
     * 等待队列（元素为 Suspension 或 callable）
     */
    protected array $waitQueue = [];

    /**
     * 是否已完成预热
     */
    protected bool $initialized = false;

    /**
     * 是否已关闭
     */
    protected bool $closed = false;

    /**
     * 统计信息
     */
    protected array $stats = [
        'total_created' => 0,
        'total_borrowed' => 0,
        'total_returned' => 0,
        'total_errors' => 0,
        'total_destroyed' => 0,
        'total_timeouts' => 0,
    ];

    /**
     * 创建连接池
     *
     * 注意：构造函数不会创建连接，可安全地在构造后再调用 setFactory()。
     *
     * @param array $config 配置选项
     * @param callable|null $factory 连接工厂
     */
    public function __construct(array $config = [], ?callable $factory = null)
    {
        $this->config = array_merge([
            'min_connections' => 1,
            'max_connections' => 10,
            'idle_timeout' => 60,
            'wait_timeout' => 5,
            'validate_on_borrow' => true,
        ], $config);

        if ($this->config['max_connections'] < 1) {
            throw new \InvalidArgumentException('max_connections 必须大于 0');
        }

        if ($this->config['min_connections'] < 0) {
            throw new \InvalidArgumentException('min_connections 不能为负数');
        }

        if ($this->config['min_connections'] > $this->config['max_connections']) {
            $this->config['min_connections'] = $this->config['max_connections'];
        }

        $this->factory = $factory;
    }

    /**
     * 设置连接工厂
     *
     * @param callable $factory 工厂回调
     * @return self
     */
    public function setFactory(callable $factory): self
    {
        $this->factory = $factory;
        return $this;
    }

    /**
     * 是否已配置工厂
     *
     * @return bool
     */
    public function hasFactory(): bool
    {
        return $this->factory !== null;
    }

    /**
     * 设置验证回调
     *
     * @param callable $validator 验证回调
     * @return self
     */
    public function setValidator(callable $validator): self
    {
        $this->validator = $validator;
        return $this;
    }

    /**
     * 设置销毁回调
     *
     * @param callable $destroyer 销毁回调
     * @return self
     */
    public function setDestroyer(callable $destroyer): self
    {
        $this->destroyer = $destroyer;
        return $this;
    }

    /**
     * 立即预热连接池（可选，正常情况下由首次借出自动触发）
     *
     * @return self
     * @throws FiberException
     */
    public function warmup(): self
    {
        $this->ensureInitialized();
        return $this;
    }

    /**
     * 获取连接
     *
     * @return mixed
     * @throws FiberException
     */
    #[\Override]
    public function getConnection(): mixed
    {
        if ($this->closed) {
            throw new FiberException('Connection pool is closed');
        }

        $this->ensureInitialized();
        $this->evictIdleConnections();

        $this->stats['total_borrowed']++;

        // 1. 优先复用空闲连接（最多重试 max_connections 次以跳过失效连接）
        $attempts = 0;
        $limit = (int)$this->config['max_connections'] + 1;

        while ($this->idleConnections !== [] && $attempts++ < $limit) {
            $entry = array_pop($this->idleConnections);
            $connection = $entry['connection'];

            if ($this->config['validate_on_borrow'] && !$this->validateConnection($connection)) {
                $this->destroyConnection($connection);
                continue;
            }

            return $this->markActive($connection);
        }

        // 2. 未达上限则新建
        if ($this->totalCount() < $this->config['max_connections']) {
            return $this->markActive($this->createConnection());
        }

        // 3. 池满，等待其他协程归还
        return $this->waitForConnection();
    }

    /**
     * 释放连接
     *
     * @param mixed $connection 连接实例
     * @return void
     */
    #[\Override]
    public function releaseConnection(mixed $connection): void
    {
        $id = $this->getConnectionId($connection);

        if (!isset($this->activeConnections[$id])) {
            return;
        }

        unset($this->activeConnections[$id]);
        $this->stats['total_returned']++;

        if ($this->closed) {
            $this->destroyConnection($connection);
            return;
        }

        // 有等待者则直接交接，避免连接在空闲队列里空转
        while ($this->waitQueue !== []) {
            $waiter = array_shift($this->waitQueue);

            if ($waiter instanceof Suspension) {
                if (!$waiter->isPending()) {
                    continue;
                }

                $this->markActive($connection);
                $waiter->resume($connection);
                return;
            }

            $this->markActive($connection);
            $waiter($connection);
            return;
        }

        $this->idleConnections[] = [
            'connection' => $connection,
            'idle_since' => Scheduler::now(),
        ];
    }

    /**
     * 借出连接执行回调，结束后自动归还
     *
     * @template T
     * @param callable $callback 接收连接的回调
     * @return mixed 回调返回值
     * @throws FiberException
     */
    public function use(callable $callback): mixed
    {
        $connection = $this->getConnection();

        try {
            return $callback($connection);
        } finally {
            $this->releaseConnection($connection);
        }
    }

    /**
     * 获取池状态
     *
     * @return array
     */
    #[\Override]
    public function getStatus(): array
    {
        return [
            'idle_count' => count($this->idleConnections),
            'active_count' => count($this->activeConnections),
            'total_count' => $this->totalCount(),
            'wait_queue_size' => count($this->waitQueue),
            'initialized' => $this->initialized,
            'closed' => $this->closed,
            'config' => $this->config,
            'stats' => $this->stats,
        ];
    }

    /**
     * 关闭连接池
     *
     * @return void
     */
    #[\Override]
    public function close(): void
    {
        $this->closed = true;

        foreach ($this->idleConnections as $entry) {
            $this->destroyConnection($entry['connection']);
        }

        foreach ($this->activeConnections as $item) {
            $this->destroyConnection($item['connection']);
        }

        $this->idleConnections = [];
        $this->activeConnections = [];

        $waiters = $this->waitQueue;
        $this->waitQueue = [];

        foreach ($waiters as $waiter) {
            if ($waiter instanceof Suspension && $waiter->isPending()) {
                $waiter->throw(new FiberException('Connection pool is closed'));
            }
        }

        $this->initialized = false;
    }

    /**
     * 惰性初始化连接池
     *
     * @return void
     * @throws FiberException
     */
    protected function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        if ($this->factory === null) {
            throw new FiberException('Connection factory not configured');
        }

        // 先置位，避免 createConnection 内部回调再次触发预热造成递归
        $this->initialized = true;

        try {
            $prewarm = (int)$this->config['min_connections'];

            for ($i = 0; $i < $prewarm; $i++) {
                $this->idleConnections[] = [
                    'connection' => $this->createConnection(),
                    'idle_since' => Scheduler::now(),
                ];
            }
        } catch (\Throwable $e) {
            $this->initialized = false;
            throw $e;
        }
    }

    /**
     * 初始化连接池（保留兼容入口）
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->ensureInitialized();
    }

    /**
     * 标记连接为活跃状态
     *
     * @param mixed $connection 连接实例
     * @return mixed
     */
    protected function markActive(mixed $connection): mixed
    {
        $this->activeConnections[$this->getConnectionId($connection)] = [
            'connection' => $connection,
            'borrowed_at' => Scheduler::now(),
        ];

        return $connection;
    }

    /**
     * 当前连接总数
     *
     * @return int
     */
    protected function totalCount(): int
    {
        return count($this->idleConnections) + count($this->activeConnections);
    }

    /**
     * 回收超时空闲连接
     *
     * @return void
     */
    protected function evictIdleConnections(): void
    {
        $idleTimeout = (float)$this->config['idle_timeout'];

        if ($idleTimeout <= 0 || $this->idleConnections === []) {
            return;
        }

        $now = Scheduler::now();
        $min = (int)$this->config['min_connections'];
        $kept = [];

        foreach ($this->idleConnections as $entry) {
            $expired = ($now - $entry['idle_since']) > $idleTimeout;

            if ($expired && (count($kept) + count($this->activeConnections)) >= $min) {
                $this->destroyConnection($entry['connection']);
                continue;
            }

            $kept[] = $entry;
        }

        $this->idleConnections = $kept;
    }

    /**
     * 创建连接
     *
     * @return mixed
     * @throws FiberException
     */
    protected function createConnection(): mixed
    {
        if ($this->factory === null) {
            throw new FiberException('Connection factory not configured');
        }

        try {
            $connection = ($this->factory)();
        } catch (\Throwable $e) {
            $this->stats['total_errors']++;
            throw new FiberException('Failed to create connection: ' . $e->getMessage(), 0, $e);
        }

        $this->stats['total_created']++;

        return $connection;
    }

    /**
     * 验证连接
     *
     * @param mixed $connection 连接实例
     * @return bool
     */
    protected function validateConnection(mixed $connection): bool
    {
        if ($this->validator !== null) {
            try {
                return (bool)($this->validator)($connection);
            } catch (\Throwable) {
                return false;
            }
        }

        return true;
    }

    /**
     * 销毁连接
     *
     * @param mixed $connection 连接实例
     * @return void
     */
    protected function destroyConnection(mixed $connection): void
    {
        $this->stats['total_destroyed']++;

        if ($this->destroyer !== null) {
            try {
                ($this->destroyer)($connection);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * 获取连接ID
     *
     * @param mixed $connection 连接实例
     * @return string
     */
    protected function getConnectionId(mixed $connection): string
    {
        if (is_object($connection)) {
            return spl_object_id($connection) . ':' . $connection::class;
        }

        return md5(serialize($connection));
    }

    /**
     * 等待可用连接
     *
     * @return mixed
     * @throws FiberException
     */
    protected function waitForConnection(): mixed
    {
        $timeout = (float)$this->config['wait_timeout'];
        $scheduler = Scheduler::current();

        // 协程上下文：挂起让权，由 releaseConnection 唤醒
        if ($scheduler !== null && Scheduler::inCoroutine()) {
            $suspension = $scheduler->park();
            $this->waitQueue[] = $suspension;

            $timer = null;

            if ($timeout > 0) {
                $timer = $scheduler->delay($timeout, function () use ($suspension): void {
                    if (!$suspension->isPending()) {
                        return;
                    }

                    $this->removeWaiter($suspension);
                    $this->stats['total_timeouts']++;
                    $suspension->throw(new FiberException('Connection pool wait timeout'));
                });
            }

            try {
                return $suspension->suspend();
            } finally {
                $timer?->cancel();
                $this->removeWaiter($suspension);
            }
        }

        // 非协程上下文：退化为带超时的阻塞轮询
        $deadline = Scheduler::now() + max($timeout, 0.0);

        do {
            if ($this->idleConnections !== []) {
                $entry = array_pop($this->idleConnections);

                return $this->markActive($entry['connection']);
            }

            if ($this->totalCount() < $this->config['max_connections']) {
                return $this->markActive($this->createConnection());
            }

            usleep(2000);
        } while (Scheduler::now() < $deadline);

        $this->stats['total_timeouts']++;

        throw new FiberException('Connection pool wait timeout');
    }

    /**
     * 从等待队列移除挂起句柄
     *
     * @param Suspension $suspension 挂起句柄
     * @return void
     */
    protected function removeWaiter(Suspension $suspension): void
    {
        foreach ($this->waitQueue as $key => $waiter) {
            if ($waiter === $suspension) {
                unset($this->waitQueue[$key]);
                $this->waitQueue = array_values($this->waitQueue);
                return;
            }
        }
    }

    /**
     * 创建PDO连接池
     *
     * @param string $dsn 数据源名称
     * @param string $username 用户名
     * @param string $password 密码
     * @param array $options PDO选项
     * @param array $poolConfig 连接池配置
     * @return self
     */
    public static function pdo(string $dsn, string $username = '', string $password = '', array $options = [], array $poolConfig = []): self
    {
        $pool = new self($poolConfig);

        $pool->setFactory(static function () use ($dsn, $username, $password, $options) {
            return new \PDO($dsn, $username, $password, $options + [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        });

        $pool->setValidator(static function (\PDO $pdo): bool {
            try {
                $pdo->query('SELECT 1');
                return true;
            } catch (\Throwable) {
                return false;
            }
        });

        $pool->setDestroyer(static function (\PDO $pdo): void {
            unset($pdo);
        });

        return $pool;
    }

    /**
     * 创建Redis连接池
     *
     * @param string $host 主机
     * @param int $port 端口
     * @param string $password 密码
     * @param int $database 数据库
     * @param array $poolConfig 连接池配置
     * @return self
     */
    public static function redis(string $host = '127.0.0.1', int $port = 6379, string $password = '', int $database = 0, array $poolConfig = []): self
    {
        $pool = new self($poolConfig);

        $pool->setFactory(static function () use ($host, $port, $password, $database) {
            $redis = new \Redis();
            $redis->connect($host, $port);

            if ($password !== '') {
                $redis->auth($password);
            }

            if ($database > 0) {
                $redis->select($database);
            }

            return $redis;
        });

        $pool->setValidator(static function (\Redis $redis): bool {
            try {
                return $redis->ping() !== false;
            } catch (\Throwable) {
                return false;
            }
        });

        $pool->setDestroyer(static function (\Redis $redis): void {
            try {
                $redis->close();
            } catch (\Throwable) {
            }
        });

        return $pool;
    }

    /**
     * 创建连接池实例
     *
     * @param array $config 配置选项
     * @param callable|null $factory 连接工厂
     * @return self
     */
    public static function make(array $config = [], ?callable $factory = null): self
    {
        return new self($config, $factory);
    }
}
