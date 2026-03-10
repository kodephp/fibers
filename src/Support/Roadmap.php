<?php

declare(strict_types=1);

namespace Kode\Fibers\Support;

class Roadmap
{
    public static function items(): array
    {
        return [
            [
                'key' => 'context_propagation',
                'title' => '上下文传递机制',
                'status' => 'in_progress',
                'target' => '2.2.x',
            ],
            [
                'key' => 'distributed_scheduler',
                'title' => '分布式 Fiber 调度',
                'status' => 'planned',
                'target' => '2.3.x',
            ],
            [
                'key' => 'profiler_dashboard',
                'title' => '性能监控面板',
                'status' => 'planned',
                'target' => '2.3.x',
            ],
            [
                'key' => 'ecosystem_bridges',
                'title' => '生态系统集成',
                'status' => 'planned',
                'target' => '2.4.x',
            ],
            [
                'key' => 'orm_adapter',
                'title' => 'ORM 适配层',
                'status' => 'planned',
                'target' => '2.4.x',
            ],
            [
                'key' => 'circuit_breaker',
                'title' => '断路器模式',
                'status' => 'planned',
                'target' => '2.5.x',
            ],
            [
                'key' => 'load_balancing',
                'title' => '负载均衡',
                'status' => 'in_progress',
                'target' => '2.2.x',
            ],
            [
                'key' => 'hot_reload',
                'title' => '热重载支持',
                'status' => 'planned',
                'target' => '2.6.x',
            ],
            [
                'key' => 'web_console',
                'title' => '可视化管理界面',
                'status' => 'planned',
                'target' => '2.6.x',
            ],
            [
                'key' => 'framework_expansion',
                'title' => '更多框架支持',
                'status' => 'in_progress',
                'target' => 'ongoing',
            ],
            [
                'key' => 'php85_compatibility',
                'title' => 'PHP 8.5 兼容与便捷 API',
                'status' => 'in_progress',
                'target' => '2.2.x',
            ],
        ];
    }
}
