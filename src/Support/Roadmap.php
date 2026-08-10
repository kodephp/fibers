<?php

declare(strict_types=1);

namespace Kode\Fibers\Support;

class Roadmap
{
    /**
     * 已交付能力清单（反映当前版本真实状态，避免对外输出误导性「规划中」信息）。
     *
     * @return array<int, array{key:string,title:string,status:string,since:string}>
     */
    public static function items(): array
    {
        return [
            [
                'key' => 'context_propagation',
                'title' => '上下文传递机制',
                'status' => 'shipped',
                'since' => '1.x',
            ],
            [
                'key' => 'distributed_scheduler',
                'title' => '分布式 Fiber 调度',
                'status' => 'shipped',
                'since' => '3.x',
            ],
            [
                'key' => 'profiler_dashboard',
                'title' => '性能监控面板',
                'status' => 'shipped',
                'since' => '2.x',
            ],
            [
                'key' => 'ecosystem_bridges',
                'title' => '生态系统集成（Swoole/Swow 桥接）',
                'status' => 'shipped',
                'since' => '4.x',
            ],
            [
                'key' => 'orm_adapter',
                'title' => 'ORM 适配层',
                'status' => 'shipped',
                'since' => '3.x',
            ],
            [
                'key' => 'circuit_breaker',
                'title' => '断路器模式',
                'status' => 'shipped',
                'since' => '3.x',
            ],
            [
                'key' => 'load_balancing',
                'title' => '负载均衡',
                'status' => 'shipped',
                'since' => '3.x',
            ],
            [
                'key' => 'hot_reload',
                'title' => '热重载支持',
                'status' => 'shipped',
                'since' => '3.x',
            ],
            [
                'key' => 'web_console',
                'title' => '可视化管理界面（WebUI）',
                'status' => 'shipped',
                'since' => '2.x',
            ],
            [
                'key' => 'framework_expansion',
                'title' => '多框架支持（Laravel/Symfony/Hyperf/Lumen/Yii3/ThinkPHP）',
                'status' => 'shipped',
                'since' => '2.x',
            ],
            [
                'key' => 'php85_compatibility',
                'title' => 'PHP 8.5 兼容与便捷 API',
                'status' => 'shipped',
                'since' => '4.x',
            ],
        ];
    }
}
