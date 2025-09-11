<?php

declare(strict_types=1);

namespace Nova\Fibers\ORM;

use Nova\Fibers\Context\Context;

/**
 * ORM适配器接口
 *
 * 为Fiber-aware ORM提供基础接口
 */
interface ORMAdapterInterface
{
    /**
     * 在Fiber上下文中执行数据库查询
     *
     * @param string $query SQL查询
     * @param array $params 查询参数
     * @param Context|null $context 上下文
     * @return array 查询结果
     */
    public function query(string $query, array $params = [], ?Context $context = null): array;

    /**
     * 在Fiber上下文中执行数据库更新操作
     *
     * @param string $query SQL更新语句
     * @param array $params 更新参数
     * @param Context|null $context 上下文
     * @return int 影响的行数
     */
    public function execute(string $query, array $params = [], ?Context $context = null): int;

    /**
     * 开始事务
     *
     * @param Context|null $context 上下文
     * @return void
     */
    public function beginTransaction(?Context $context = null): void;

    /**
     * 提交事务
     *
     * @param Context|null $context 上下文
     * @return void
     */
    public function commit(?Context $context = null): void;

    /**
     * 回滚事务
     *
     * @param Context|null $context 上下文
     * @return void
     */
    public function rollback(?Context $context = null): void;

    /**
     * 获取连接状态
     *
     * @return array 连接状态信息
     */
    public function getConnectionStatus(): array;
}
