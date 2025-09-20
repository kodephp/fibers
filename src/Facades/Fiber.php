<?php

namespace Nova\Fibers\Facades;

use Nova\Fibers\FiberManager;

/**
 * Fiber - 纤程门面类
 * 
 * 提供便捷的静态方法访问纤程功能
 * 
 * @method static mixed run(callable $task, float $timeout = null)
 * @method static \Nova\Fibers\Core\FiberPool pool(array $options = [])
 * @method static \Nova\Fibers\Channel\Channel channel(string $name, int $buffer = 0)
 */
class Fiber
{
    /**
     * 运行一个纤程任务
     *
     * @param callable $task 任务回调
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 任务返回值
     */
    public static function run(callable $task, ?float $timeout = null)
    {
        return FiberManager::run($task, $timeout);
    }

    /**
     * 获取纤程池实例
     *
     * @param array $options 池配置选项
     * @return \Nova\Fibers\Core\FiberPool 纤程池实例
     */
    public static function pool(array $options = [])
    {
        return FiberManager::pool($options);
    }

    /**
     * 创建或获取通道实例
     *
     * @param string $name 通道名称
     * @param int $buffer 缓冲区大小
     * @return \Nova\Fibers\Channel\Channel 通道实例
     */
    public static function channel(string $name, int $buffer = 0)
    {
        return FiberManager::channel($name, $buffer);
    }
}