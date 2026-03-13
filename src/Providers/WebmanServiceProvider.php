<?php

declare(strict_types=1);

namespace Kode\Fibers\Providers;

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Support\CpuInfo;

/**
 * Webman 服务提供者
 *
 * 为 Webman 框架提供 Fiber 支持。
 */
class WebmanServiceProvider
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
        $this->app->instance(FiberPool::class, function () {
            $config = $this->getConfig();
            return new FiberPool($this->mergeConfig($config));
        });
    }

    /**
     * 获取配置
     *
     * @return array
     */
    protected function getConfig(): array
    {
        if (function_exists('\\config')) {
            return \config('fibers.default_pool', []);
        }
        
        return [];
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot(): void
    {
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
