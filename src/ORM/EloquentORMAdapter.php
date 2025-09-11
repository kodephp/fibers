<?php

declare(strict_types=1);

namespace Nova\Fibers\ORM;

use Nova\Fibers\Context\Context;
use Nova\Fibers\Context\ContextManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;

/**
 * Eloquent ORM适配器实现
 *
 * 为Laravel Eloquent提供Fiber-aware支持
 */
class EloquentORMAdapter implements ORMAdapterInterface
{
    /**
     * @var Capsule Eloquent Capsule实例
     */
    private Capsule $capsule;

    /**
     * @var ConnectionInterface 数据库连接
     */
    private ConnectionInterface $connection;

    /**
     * 构造函数
     *
     * @param array $config 数据库配置
     */
    public function __construct(array $config = [])
    {
        $this->capsule = new Capsule();

        // 添加连接配置
        $this->capsule->addConnection(array_merge([
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'database'  => 'test',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ], $config));

        // 设置全局静态可访问
        $this->capsule->setAsGlobal();

        // 启动Eloquent
        $this->capsule->bootEloquent();

        // 获取连接
        $this->connection = $this->capsule->getConnection();
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $query, array $params = [], ?Context $context = null): array
    {
        // 设置上下文
        if ($context !== null) {
            ContextManager::setCurrentContext($context);
        }

        // 执行查询
        $statement = $this->connection->prepare($query);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $query, array $params = [], ?Context $context = null): int
    {
        // 设置上下文
        if ($context !== null) {
            ContextManager::setCurrentContext($context);
        }

        // 执行更新操作
        $statement = $this->connection->prepare($query);
        $statement->execute($params);

        return $statement->rowCount();
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction(?Context $context = null): void
    {
        // 设置上下文
        if ($context !== null) {
            ContextManager::setCurrentContext($context);
        }

        $this->connection->beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commit(?Context $context = null): void
    {
        // 设置上下文
        if ($context !== null) {
            ContextManager::setCurrentContext($context);
        }

        $this->connection->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollback(?Context $context = null): void
    {
        // 设置上下文
        if ($context !== null) {
            ContextManager::setCurrentContext($context);
        }

        $this->connection->rollBack();
    }

    /**
     * {@inheritDoc}
     */
    public function getConnectionStatus(): array
    {
        try {
            $this->connection->select('SELECT 1');
            return [
                'status' => 'connected',
                'driver' => $this->connection->getDriverName(),
                'database' => $this->connection->getDatabaseName()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取Eloquent Capsule实例
     *
     * @return Capsule
     */
    public function getCapsule(): Capsule
    {
        return $this->capsule;
    }

    /**
     * 获取数据库连接
     *
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }
}
