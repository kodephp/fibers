<?php

declare(strict_types=1);

namespace Kode\Fibers\Providers;

use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\ServiceProvider\ServiceProviderInterface;
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Fibers;

/**
 * Yii3服务提供者
 */
class Yii3ServiceProvider implements ServiceProviderInterface
{
    /**
     * 注册服务
     *
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        // 注册FiberPool服务
        $container->set(FiberPool::class, static function (Container $container) {
            $config = $container->get('config')->get('fibers.default_pool', []);
            return new FiberPool($config);
        });
        
        // 注册Channels
        $container->set('fibers.channels', static function (Container $container) {
            $channels = [];
            $config = $container->get('config')->get('fibers.channels', []);
            
            foreach ($config as $name => $channelConfig) {
                $bufferSize = $channelConfig['buffer_size'] ?? 0;
                $channels[$name] = Channel::make($name, $bufferSize);
            }
            
            return $channels;
        });
        
        // 注册Fibers服务
        $container->set(Fibers::class, static function () {
            return new Fibers();
        });
    }
    
    /**
     * 获取配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'fibers' => [
                'default_pool' => [
                    'size' => 32,
                    'max_exec_time' => 30,
                    'gc_interval' => 100,
                ],
                'channels' => [],
                'features' => [
                    'auto_suspend_io' => true,
                    'enable_monitoring' => true,
                    'strict_destruct_check' => true,
                ],
            ],
        ];
    }
    
    /**
     * 引导服务
     *
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void
    {
        // 引导逻辑
        // 在实际实现中，这里可以进行服务引导
    }
}