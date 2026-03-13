<?php

declare(strict_types=1);

namespace Kode\Fibers\Pool;

/**
 * 连接池接口
 *
 * 定义连接池的基本操作。
 */
interface ConnectionPoolInterface
{
    /**
     * 获取连接
     *
     * @return mixed
     */
    public function getConnection(): mixed;

    /**
     * 释放连接
     *
     * @param mixed $connection 连接实例
     * @return void
     */
    public function releaseConnection(mixed $connection): void;

    /**
     * 获取池状态
     *
     * @return array
     */
    public function getStatus(): array;

    /**
     * 关闭连接池
     *
     * @return void
     */
    public function close(): void;
}
