<?php

declare(strict_types=1);

namespace Kode\Fibers\Providers;

use think\Service;
use think\App;
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Fibers;

/**
 * ThinkPHP8服务提供者
 */
class ThinkPHPService extends Service
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register()
    {
        // 注册FiberPool单例
        $this->app->singleton('fiber.pool', function (App $app) {
            $config = $app->config->get('fibers.default_pool', []);
            return new FiberPool($config);
        });
        
        // 注册FiberPool类绑定
        $this->app->bind(FiberPool::class, 'fiber.pool');
        
        // 注册Channels
        $this->app->singleton('fibers.channels', function (App $app) {
            $channels = [];
            $config = $app->config->get('fibers.channels', []);
            
            foreach ($config as $name => $channelConfig) {
                $bufferSize = $channelConfig['buffer_size'] ?? 0;
                $channels[$name] = Channel::make($name, $bufferSize);
            }
            
            return $channels;
        });
        
        // 注册Fibers服务
        $this->app->singleton(Fibers::class, function () {
            return new Fibers();
        });
    }
    
    /**
     * 引导应用
     *
     * @return void
     */
    public function boot()
    {
        // 注册命令
        $this->commands([
            \Kode\Fibers\Commands\FibersCommand::class,
        ]);
        
        // 发布配置文件
        $this->publishConfig();
    }
    
    /**
     * 发布配置文件
     *
     * @return void
     */
    protected function publishConfig()
    {
        // 在实际实现中，这里应该发布配置文件到ThinkPHP应用目录
    }
    
    /**
     * 获取配置路径
     *
     * @return string
     */
    public static function getConfigPath()
    {
        return __DIR__ . '/../config/fibers.php';
    }
}