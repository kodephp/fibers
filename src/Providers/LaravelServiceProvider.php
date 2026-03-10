<?php

declare(strict_types=1);

namespace Kode\Fibers\Providers;

use Illuminate\Support\ServiceProvider;
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Fibers;

/**
 * Laravel服务提供者
 */
class LaravelServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register()
    {
        // 注册FiberPool单例
        $this->app->singleton(FiberPool::class, function () {
            $config = config('fibers.default_pool', []);
            return new FiberPool($config);
        });
        
        // 注册Channels
        $this->app->singleton('fibers.channels', function () {
            $channels = [];
            $config = config('fibers.channels', []);
            
            foreach ($config as $name => $channelConfig) {
                $bufferSize = $channelConfig['buffer_size'] ?? 0;
                $channels[$name] = Channel::make($name, $bufferSize);
            }
            
            return $channels;
        });
        
        // 注册Fibers门面
        $this->app->singleton(Fibers::class, function () {
            return new Fibers();
        });
    }
    
    /**
     * 引导应用程序
     *
     * @return void
     */
    public function boot()
    {
        // 发布配置文件
        $this->publishes([
            dirname(__DIR__, 2) . '/config/fibers.php' => config_path('fibers.php'),
        ], 'fibers-config');
        
        // 注册中间件
        if (config('fibers.laravel.middleware.enable_fibers', true)) {
            $this->registerMiddleware();
        }
        
        // 注册命令
        $this->commands([
            \Kode\Fibers\Commands\FibersCommand::class,
        ]);
    }
    
    /**
     * 注册中间件
     *
     * @return void
     */
    protected function registerMiddleware()
    {
        // 注册Fiber中间件
        // 在实际实现中，这里应该注册Laravel中间件
    }
}
