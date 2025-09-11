<?php

declare(strict_types=1);

namespace Nova\Fibers\ORM;

use Nova\Fibers\Context\Context;
use Nova\Fibers\Context\ContextManager;

/**
 * Fixtures适配器
 *
 * 在Fiber环境中处理数据fixtures
 */
class FixturesAdapter
{
    /**
     * @var ORMAdapterInterface ORM适配器
     */
    private ORMAdapterInterface $ormAdapter;

    /**
     * 构造函数
     *
     * @param ORMAdapterInterface $ormAdapter ORM适配器
     */
    public function __construct(ORMAdapterInterface $ormAdapter)
    {
        $this->ormAdapter = $ormAdapter;
    }

    /**
     * 加载fixtures数据
     *
     * @param array $fixtures fixtures数据
     * @param Context|null $context 上下文
     * @return int 插入的记录数
     */
    public function load(array $fixtures, ?Context $context = null): int
    {
        $totalInserted = 0;

        // 设置上下文
        if ($context !== null) {
            ContextManager::setCurrentContext($context);
        }

        // 开始事务
        $this->ormAdapter->beginTransaction($context);

        try {
            foreach ($fixtures as $table => $data) {
                foreach ($data as $row) {
                    // 构建INSERT语句
                    $columns = array_keys($row);
                    $placeholders = array_fill(0, count($columns), '?');

                    $query = sprintf(
                        "INSERT INTO %s (%s) VALUES (%s)",
                        $table,
                        implode(', ', $columns),
                        implode(', ', $placeholders)
                    );

                    // 执行插入
                    $inserted = $this->ormAdapter->execute($query, array_values($row), $context);
                    $totalInserted += $inserted;
                }
            }

            // 提交事务
            $this->ormAdapter->commit($context);
        } catch (\Exception $e) {
            // 回滚事务
            $this->ormAdapter->rollback($context);
            throw $e;
        }

        return $totalInserted;
    }

    /**
     * 清空fixtures数据
     *
     * @param array $tables 表名数组
     * @param Context|null $context 上下文
     * @return int 删除的记录数
     */
    public function purge(array $tables, ?Context $context = null): int
    {
        $totalDeleted = 0;

        // 设置上下文
        if ($context !== null) {
            ContextManager::setCurrentContext($context);
        }

        // 开始事务
        $this->ormAdapter->beginTransaction($context);

        try {
            // 禁用外键约束
            $this->ormAdapter->execute("SET FOREIGN_KEY_CHECKS = 0", [], $context);

            foreach ($tables as $table) {
                // 清空表数据
                $deleted = $this->ormAdapter->execute("DELETE FROM {$table}", [], $context);
                $totalDeleted += $deleted;
            }

            // 启用外键约束
            $this->ormAdapter->execute("SET FOREIGN_KEY_CHECKS = 1", [], $context);

            // 提交事务
            $this->ormAdapter->commit($context);
        } catch (\Exception $e) {
            // 回滚事务
            $this->ormAdapter->rollback($context);
            // 重新启用外键约束
            $this->ormAdapter->execute("SET FOREIGN_KEY_CHECKS = 1", [], $context);
            throw $e;
        }

        return $totalDeleted;
    }

    /**
     * 获取fixtures状态
     *
     * @param Context|null $context 上下文
     * @return array fixtures状态信息
     */
    public function getStatus(?Context $context = null): array
    {
        // 设置上下文
        if ($context !== null) {
            ContextManager::setCurrentContext($context);
        }

        // 获取数据库连接状态
        $connectionStatus = $this->ormAdapter->getConnectionStatus();

        return [
            'connection' => $connectionStatus,
            'adapter' => get_class($this->ormAdapter)
        ];
    }
}
