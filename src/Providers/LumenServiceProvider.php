<?php

declare(strict_types=1);

namespace Kode\Fibers\Providers;

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Support\CpuInfo;

/**
 * Lumen 服务提供者
 *
 * 为 Lumen 框架提供 Fiber 支持。
 */
class LumenServiceProvider
{
    /**
     * 应用实例
     */
    protected $app;

    /**
     * 创建服务提供者实例
     *
     * @param mixed $app 应用实例
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(FiberPool::class, function () {
            $config = $this->app->make('config')->get('fibers.default_pool', []);
            return new FiberPool($this->mergeConfig($config));
        });
        
        $this->app->alias(FiberPool::class, 'fibers.pool');
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot(): void
    {
        $configPath = dirname(__DIR__, 2) . '/config/fibers.php';
        
        if (file_exists($configPath)) {
            $this->app->make('config')->set('fibers', require $configPath);
        }
    }

    /**
     * 合并配置
     *
     * @param array $config 用户配置
     * @return array
     */
    protected function mergeConfig(array $config): array
    {
        return array_merge([
            'size' => CpuInfo::getRecommendedPoolSize(4),
            'max_exec_time' => 30,
            'max_retries' => 3,
            'retry_delay' => 0.5,
        ], $config);
    }
}
