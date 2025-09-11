<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Fiber Pool Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the default configuration for fiber pools used
    | throughout your application. The size of the pool will determine
    | how many concurrent fibers can be executed.
    |
    */

    'default_pool' => [
        'size' => env('FIBER_POOL_SIZE', \Nova\Fibers\Support\CpuInfo::get() * 4),
        'timeout' => 30,
        'max_retries' => 3,
        'context' => [
            'user_id' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the configuration for channels used in your
    | application. Channels provide a way to communicate between fibers.
    |
    */

    'channels' => [
        'default' => [
            'buffer_size' => 0,
        ],
        'orders' => [
            'buffer_size' => 100,
        ],
        'logs' => [
            'buffer_size' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may enable or disable specific features of the Nova Fibers
    | package. This allows you to customize the behavior of the package
    | to suit your application's needs.
    |
    */

    'features' => [
        'auto_suspend_io' => true,
        'enable_monitoring' => true,
        'strict_destruct_check' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the configuration for the distributed scheduler.
    | This includes settings for cluster nodes and task execution.
    |
    */

    'scheduler' => [
        'driver' => 'local',
        'local' => [
            'size' => \Nova\Fibers\Support\CpuInfo::get() * 2,
            'max_exec_time' => 60,
        ],
        'distributed' => [
            'nodes' => [],
            'port' => 8000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiler Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the fiber profiler settings. This includes
    | enabling/disabling the profiler and setting up the web panel.
    |
    */

    'profiler' => [
        'enabled' => env('FIBER_PROFILER_ENABLED', false),
        'web_panel' => [
            'enabled' => env('FIBER_PROFILER_WEB_ENABLED', false),
            'host' => 'localhost',
            'port' => 8080,
        ],
    ],
];
