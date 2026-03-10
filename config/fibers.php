<?php

declare(strict_types=1);

use function Kode\Fibers\Support\env;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Fiber Pool Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the default configuration for fiber pools.
    |
    */
    
    'default_pool' => [
        'size' => env('FIBER_POOL_SIZE', \Kode\Fibers\Support\CpuInfo::get() * 4),
        'max_exec_time' => 30,
        'gc_interval' => 100,
        'max_retries' => 3,  // 任务最大重试次数
        'retry_delay' => 1,  // 重试延迟（秒）
        'context' => [],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Channels Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the configuration for fiber channels.
    |
    */
    
    'channels' => [
        // 'example' => ['buffer_size' => 100],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Features Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may enable or disable specific features.
    |
    */
    
    'features' => [
        'auto_suspend_io' => true,
        'enable_monitoring' => true,
        'strict_destruct_check' => version_compare(PHP_VERSION, '8.4.0', '<'), // 在PHP < 8.4时启用严格析构检查
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Framework Integration
    |--------------------------------------------------------------------------
    |
    | Here you may configure framework-specific settings.
    |
    */
    
    'framework' => [
        'name' => env('APP_FRAMEWORK', 'default'),
        'service_provider' => true,
        'provider_class' => env('FIBER_PROVIDER_CLASS', 'Kode\\Fibers\\Providers\\GenericProvider'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Environment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration related to the runtime environment.
    |
    */
    
    'environment' => [
        'php_version' => PHP_VERSION,
        'cpu_cores' => \Kode\Fibers\Support\CpuInfo::get(),
        'detected_framework' => 'default',
    ],
];