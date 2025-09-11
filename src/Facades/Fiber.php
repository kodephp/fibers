<?php

declare(strict_types=1);

namespace Nova\Fibers\Facades;

use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Channel\Channel;
use Nova\Fibers\Support\Environment;
use Fiber as PhpFiber;

/**
 * Fiber 门面类
 *
 * @package Nova\Fibers\Facades
 *
 * @method static mixed run(callable $task, float $timeout = null)
 * @method static FiberPool pool(array $options = [])
 * @method static Channel channel(string $name, int $buffer = 0)
 */
class Fiber
{
    /**
     * @var bool 是否启用安全析构模式
     */
    protected static bool $safeDestructMode = false;

    /**
     * 一键运行纤程任务
     *
     * @param callable $task 任务
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 任务结果
     */
    public static function run(callable $task, ?float $timeout = null): mixed
    {
        $fiber = new PhpFiber($task);
        $fiber->start();

        if ($timeout !== null) {
            // 简单的超时实现
            $start = microtime(true);
            while (!$fiber->isTerminated()) {
                if (microtime(true) - $start > $timeout) {
                    throw new \RuntimeException('Fiber execution timeout');
                }
                usleep(1000); // 1ms
            }
        } else {
            // 等待纤程完成
            while (!$fiber->isTerminated()) {
                usleep(1000); // 1ms
            }
        }

        return $fiber->getReturn();
    }

    /**
     * 创建纤程池
     *
     * @param array $options 配置选项
     * @return FiberPool 纤程池实例
     */
    public static function pool(array $options = []): FiberPool
    {
        return new FiberPool($options);
    }

    /**
     * 创建通信通道
     *
     * @param string $name 通道名称
     * @param int $buffer 缓冲区大小
     * @return Channel 通道实例
     */
    public static function channel(string $name, int $buffer = 0): Channel
    {
        return Channel::make($name, $buffer);
    }

    /**
     * 启用安全析构模式
     *
     * @return void
     */
    public static function enableSafeDestructMode(): void
    {
        self::$safeDestructMode = true;
    }

    /**
     * 检查是否启用安全析构模式
     *
     * @return bool
     */
    public static function isSafeDestructMode(): bool
    {
        return self::$safeDestructMode;
    }

    /**
     * 静态方法调用处理
     *
     * @param string $name 方法名
     * @param array $arguments 参数
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        // 可以在这里添加更多门面方法
        throw new \BadMethodCallException("Method {$name} not found");
    }
}
