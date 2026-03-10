<?php

declare(strict_types=1);

namespace Kode\Fibers\Providers;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Fibers;

/**
 * Symfony Bundle for Kode/Fibers
 */
class SymfonyBundle extends AbstractBundle
{
    /**
     * 构建容器
     *
     * @param ContainerBuilder $container
     * @param array $config
     * @return void
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        
        // 注册服务
        $this->registerServices($container);
    }
    
    /**
     * 加载配置
     *
     * @param array $config
     * @param ContainerConfigurator $container
     * @param ContainerBuilder $builder
     * @return void
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder)
    {
        // 注册FiberPool服务
        $container->services()
            ->set(FiberPool::class)
            ->args([$config['default_pool'] ?? []])
            ->public();
        
        // 注册Fibers服务
        $container->services()
            ->set(Fibers::class)
            ->public();
        
        // 注册命令
        $container->services()
            ->set(\Kode\Fibers\Commands\FibersCommand::class)
            ->tag('console.command');
    }
    
    /**
     * 获取配置目录
     *
     * @return string
     */
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
    
    /**
     * 注册服务
     *
     * @param ContainerBuilder $container
     * @return void
     */
    private function registerServices(ContainerBuilder $container)
    {
        // 在实际实现中，这里可以注册更多服务
    }
    
    /**
     * 配置的默认值
     *
     * @return array
     */
    public function getDefaultConfiguration(): array
    {
        return [
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
        ];
    }
}