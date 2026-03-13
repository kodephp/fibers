<?php

declare(strict_types=1);

namespace Kode\Fibers\Pool;

use Kode\Fibers\Exceptions\FiberException;

/**
 * 通用连接池
 *
 * 提供可复用的连接管理，支持数据库、Redis、HTTP等连接类型。
 */
class ConnectionPool implements ConnectionPoolInterface
{
    /**
     * 连接池配置
     */
    protected array $config;

    /**
     * 连接工厂回调
     */
    protected $factory;

    /**
     * 验证回调
     */
    protected $validator;

    /**
     * 销毁回调
     */
    protected $destroyer;

    /**
     * 空闲连接队列
     */
    protected array $idleConnections = [];

    /**
     * 活跃连接映射
     */
    protected array $activeConnections = [];

    /**
     * 等待队列
     */
    protected array $waitQueue = [];

    /**
     * 统计信息
     */
    protected array $stats = [
        'total_created' => 0,
        'total_borrowed' => 0,
        'total_returned' => 0,
        'total_errors' => 0,
    ];

    /**
     * 创建连接池
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
        
        $this->factory = $factory;
        
        $this->initialize();
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
     * 获取连接
     *
     * @return mixed
     * @throws FiberException
     */
    public function getConnection(): mixed
    {
        $this->stats['total_borrowed']++;
        
        if (!empty($this->idleConnections)) {
            $connection = array_pop($this->idleConnections);
            
            if ($this->config['validate_on_borrow'] && !$this->validateConnection($connection)) {
                $this->destroyConnection($connection);
                return $this->getConnection();
            }
            
            $id = $this->getConnectionId($connection);
            $this->activeConnections[$id] = [
                'connection' => $connection,
                'borrowed_at' => time(),
            ];
            
            return $connection;
        }
        
        $totalCount = count($this->idleConnections) + count($this->activeConnections);
        
        if ($totalCount < $this->config['max_connections']) {
            $connection = $this->createConnection();
            $id = $this->getConnectionId($connection);
            $this->activeConnections[$id] = [
                'connection' => $connection,
                'borrowed_at' => time(),
            ];
            
            return $connection;
        }
        
        return $this->waitForConnection();
    }

    /**
     * 释放连接
     *
     * @param mixed $connection 连接实例
     * @return void
     */
    public function releaseConnection(mixed $connection): void
    {
        $id = $this->getConnectionId($connection);
        
        if (!isset($this->activeConnections[$id])) {
            return;
        }
        
        unset($this->activeConnections[$id]);
        $this->stats['total_returned']++;
        
        if (!empty($this->waitQueue)) {
            $waiter = array_shift($this->waitQueue);
            $this->activeConnections[$id] = [
                'connection' => $connection,
                'borrowed_at' => time(),
            ];
            $waiter($connection);
            return;
        }
        
        $this->idleConnections[] = $connection;
    }

    /**
     * 获取池状态
     *
     * @return array
     */
    public function getStatus(): array
    {
        return [
            'idle_count' => count($this->idleConnections),
            'active_count' => count($this->activeConnections),
            'total_count' => count($this->idleConnections) + count($this->activeConnections),
            'wait_queue_size' => count($this->waitQueue),
            'config' => $this->config,
            'stats' => $this->stats,
        ];
    }

    /**
     * 关闭连接池
     *
     * @return void
     */
    public function close(): void
    {
        foreach ($this->idleConnections as $connection) {
            $this->destroyConnection($connection);
        }
        
        foreach ($this->activeConnections as $item) {
            $this->destroyConnection($item['connection']);
        }
        
        $this->idleConnections = [];
        $this->activeConnections = [];
        $this->waitQueue = [];
    }

    /**
     * 初始化连接池
     *
     * @return void
     */
    protected function initialize(): void
    {
        for ($i = 0; $i < $this->config['min_connections']; $i++) {
            $connection = $this->createConnection();
            $this->idleConnections[] = $connection;
        }
    }

    /**
     * 创建连接
     *
     * @return mixed
     * @throws FiberException
     */
    protected function createConnection(): mixed
    {
        if (!$this->factory) {
            throw new FiberException('Connection factory not configured');
        }
        
        $this->stats['total_created']++;
        
        try {
            return ($this->factory)();
        } catch (\Throwable $e) {
            $this->stats['total_errors']++;
            throw new FiberException('Failed to create connection: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 验证连接
     *
     * @param mixed $connection 连接实例
     * @return bool
     */
    protected function validateConnection(mixed $connection): bool
    {
        if ($this->validator) {
            try {
                return ($this->validator)($connection);
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
        if ($this->destroyer) {
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
            return spl_object_id($connection) . ':' . get_class($connection);
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
        $startTime = time();
        $timeout = $this->config['wait_timeout'];
        
        while (time() - $startTime < $timeout) {
            if (!empty($this->idleConnections)) {
                return $this->getConnection();
            }
            
            usleep(10000);
        }
        
        throw new FiberException('Connection pool wait timeout');
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
        
        $pool->setFactory(function () use ($dsn, $username, $password, $options) {
            return new \PDO($dsn, $username, $password, $options + [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        });
        
        $pool->setValidator(function (\PDO $pdo) {
            try {
                $pdo->query('SELECT 1');
                return true;
            } catch (\Throwable) {
                return false;
            }
        });
        
        $pool->setDestroyer(function (\PDO $pdo) {
            $pdo = null;
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
        
        $pool->setFactory(function () use ($host, $port, $password, $database) {
            $redis = new \Redis();
            $redis->connect($host, $port);
            
            if (!empty($password)) {
                $redis->auth($password);
            }
            
            if ($database > 0) {
                $redis->select($database);
            }
            
            return $redis;
        });
        
        $pool->setValidator(function (\Redis $redis) {
            try {
                return $redis->ping() === '+PONG';
            } catch (\Throwable) {
                return false;
            }
        });
        
        $pool->setDestroyer(function (\Redis $redis) {
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
     * @return self
     */
    public static function make(array $config = []): self
    {
        return new self($config);
    }
}
