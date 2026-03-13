<?php

declare(strict_types=1);

namespace Kode\Fibers\Providers;

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Support\CpuInfo;

/**
 * Hyperf 服务提供者
 *
 * 为 Hyperf 框架提供 Fiber 支持。
 */
class HyperfServiceProvider
{
    /**
     * 应用实例
     */
    protected $container;

    /**
     * 创建服务提供者实例
     *
     * @param mixed $container 容器实例
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        $this->container->set(FiberPool::class, function () {
            $poolConfig = $this->getConfig();
            return new FiberPool($this->mergeConfig($poolConfig));
        });
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
     * 获取配置
     *
     * @return array
     */
    protected function getConfig(): array
    {
        if ($this->container->has('config')) {
            $config = $this->container->get('config');
            if (is_object($config) && method_exists($config, 'get')) {
                return $config->get('fibers.default_pool', []);
            }
            if (is_array($config)) {
                return $config['fibers']['default_pool'] ?? [];
            }
        }
        
        return [];
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
