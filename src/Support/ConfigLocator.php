<?php

namespace Nova\Fibers\Support;

/**
 * ConfigLocator - 配置文件定位器
 * 
 * 用于在不同框架环境中定位和加载配置文件
 */
class ConfigLocator
{
    /**
     * 查找配置文件路径
     *
     * @param string $filename 配置文件名
     * @return string|null 配置文件路径或null（未找到）
     */
    public static function locate(string $filename): ?string
    {
        // 常见的配置文件路径
        $possiblePaths = [
            // Laravel
            base_path("config/{$filename}"),
            
            // Symfony
            self::getSymfonyConfigPath($filename),
            
            // Yii3
            self::getYiiConfigPath($filename),
            
            // ThinkPHP
            self::getThinkPHPConfigPath($filename),
            
            // 项目根目录
            getcwd() . "/config/{$filename}",
            
            // 包目录
            __DIR__ . "/../../config/{$filename}",
            
            // 当前工作目录
            "./config/{$filename}",
        ];

        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * 获取Symfony配置文件路径
     *
     * @param string $filename 配置文件名
     * @return string|null 配置文件路径或null（未找到）
     */
    private static function getSymfonyConfigPath(string $filename): ?string
    {
        // 检查Symfony环境
        if (class_exists('\Symfony\Component\HttpKernel\Kernel')) {
            // 尝试获取项目根目录
            $projectDir = defined('PROJECT_ROOT') ? PROJECT_ROOT : 
                         (defined('APP_PATH') ? APP_PATH : 
                         (getenv('APP_PATH') ?: null));
            
            if ($projectDir) {
                return $projectDir . "/config/packages/{$filename}";
            }
        }

        return null;
    }

    /**
     * 获取Yii3配置文件路径
     *
     * @param string $filename 配置文件名
     * @return string|null 配置文件路径或null（未找到）
     */
    private static function getYiiConfigPath(string $filename): ?string
    {
        // 检查Yii3环境
        if (class_exists('\Yiisoft\Yii\Console\Application') || 
            class_exists('\Yiisoft\Yii\Web\Application')) {
            // 尝试获取项目根目录
            $projectDir = defined('YII_PROJECT_ROOT') ? YII_PROJECT_ROOT : 
                         (getenv('YII_PROJECT_ROOT') ?: null);
            
            if ($projectDir) {
                return $projectDir . "/config/{$filename}";
            }
        }

        return null;
    }

    /**
     * 获取ThinkPHP配置文件路径
     *
     * @param string $filename 配置文件名
     * @return string|null 配置文件路径或null（未找到）
     */
    private static function getThinkPHPConfigPath(string $filename): ?string
    {
        // 检查ThinkPHP环境
        if (defined('THINK_PATH') || defined('APP_PATH')) {
            $appPath = defined('APP_PATH') ? APP_PATH : 
                      (defined('ROOT_PATH') ? ROOT_PATH . 'application/' : null);
            
            if ($appPath) {
                return $appPath . "/config/{$filename}";
            }
        }

        return null;
    }

    /**
     * 生成框架特定的配置文件
     *
     * @param string $framework 框架名称
     * @param string $targetPath 目标路径
     * @return bool 是否成功生成
     */
    public static function generateFrameworkConfig(string $framework, string $targetPath): bool
    {
        $template = self::getFrameworkConfigTemplate($framework);
        
        if ($template === null) {
            return false;
        }

        // 确保目标目录存在
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($targetPath, $template) !== false;
    }

    /**
     * 获取框架配置模板
     *
     * @param string $framework 框架名称
     * @return string|null 配置模板或null（不支持的框架）
     */
    private static function getFrameworkConfigTemplate(string $framework): ?string
    {
        switch (strtolower($framework)) {
            case 'laravel':
                return self::getLaravelConfigTemplate();
                
            case 'symfony':
                return self::getSymfonyConfigTemplate();
                
            case 'yii3':
                return self::getYiiConfigTemplate();
                
            case 'thinkphp':
                return self::getThinkPHPConfigTemplate();
                
            default:
                return self::getDefaultConfigTemplate();
        }
    }

    /**
     * 获取Laravel配置模板
     *
     * @return string Laravel配置模板
     */
    private static function getLaravelConfigTemplate(): string
    {
        return <<<PHP
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
        'size' => env('FIBER_POOL_SIZE', \\Nova\\Fibers\\Support\\CpuInfo::get() * 4),
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
            'size' => \\Nova\\Fibers\\Support\\CpuInfo::get() * 2,
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
PHP;
    }

    /**
     * 获取Symfony配置模板
     *
     * @return string Symfony配置模板
     */
    private static function getSymfonyConfigTemplate(): string
    {
        return <<<YAML
# Fiber configuration for Symfony
nova_fibers:
    default_pool:
        size: '%env(int:FIBER_POOL_SIZE)%'
        timeout: 30
        max_retries: 3
        context:
            user_id: null

    channels:
        default:
            buffer_size: 0
        orders:
            buffer_size: 100
        logs:
            buffer_size: 50

    features:
        auto_suspend_io: true
        enable_monitoring: true
        strict_destruct_check: true

    scheduler:
        driver: 'local'
        local:
            size: '%env(int:FIBER_POOL_SIZE)%'
            max_exec_time: 60
        distributed:
            nodes: []
            port: 8000

    profiler:
        enabled: '%env(bool:FIBER_PROFILER_ENABLED)%'
        web_panel:
            enabled: '%env(bool:FIBER_PROFILER_WEB_ENABLED)%'
            host: 'localhost'
            port: 8080
YAML;
    }

    /**
     * 获取Yii3配置模板
     *
     * @return string Yii3配置模板
     */
    private static function getYiiConfigTemplate(): string
    {
        return <<<PHP
<?php

return [
    'nova/fibers' => [
        'default_pool' => [
            'size' => env('FIBER_POOL_SIZE') ?: \\Nova\\Fibers\\Support\\CpuInfo::get() * 4,
            'timeout' => 30,
            'max_retries' => 3,
            'context' => [
                'user_id' => null,
            ],
        ],

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

        'features' => [
            'auto_suspend_io' => true,
            'enable_monitoring' => true,
            'strict_destruct_check' => true,
        ],

        'scheduler' => [
            'driver' => 'local',
            'local' => [
                'size' => \\Nova\\Fibers\\Support\\CpuInfo::get() * 2,
                'max_exec_time' => 60,
            ],
            'distributed' => [
                'nodes' => [],
                'port' => 8000,
            ],
        ],

        'profiler' => [
            'enabled' => env('FIBER_PROFILER_ENABLED', false),
            'web_panel' => [
                'enabled' => env('FIBER_PROFILER_WEB_ENABLED', false),
                'host' => 'localhost',
                'port' => 8080,
            ],
        ],
    ],
];
PHP;
    }

    /**
     * 获取ThinkPHP配置模板
     *
     * @return string ThinkPHP配置模板
     */
    private static function getThinkPHPConfigTemplate(): string
    {
        return <<<PHP
<?php

return [
    // Default Fiber Pool Configuration
    'default_pool' => [
        'size' => env('FIBER_POOL_SIZE', \\Nova\\Fibers\\Support\\CpuInfo::get() * 4),
        'timeout' => 30,
        'max_retries' => 3,
        'context' => [
            'user_id' => null,
        ],
    ],

    // Channels Configuration
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

    // Features Configuration
    'features' => [
        'auto_suspend_io' => true,
        'enable_monitoring' => true,
        'strict_destruct_check' => true,
    ],

    // Scheduler Configuration
    'scheduler' => [
        'driver' => 'local',
        'local' => [
            'size' => \\Nova\\Fibers\\Support\\CpuInfo::get() * 2,
            'max_exec_time' => 60,
        ],
        'distributed' => [
            'nodes' => [],
            'port' => 8000,
        ],
    ],

    // Profiler Configuration
    'profiler' => [
        'enabled' => env('FIBER_PROFILER_ENABLED', false),
        'web_panel' => [
            'enabled' => env('FIBER_PROFILER_WEB_ENABLED', false),
            'host' => 'localhost',
            'port' => 8080,
        ],
    ],
];
PHP;
    }

    /**
     * 获取默认配置模板
     *
     * @return string 默认配置模板
     */
    private static function getDefaultConfigTemplate(): string
    {
        return <<<PHP
<?php

return [
    'default_pool' => [
        'size' => \\Nova\\Fibers\\Support\\CpuInfo::get() * 4,
        'timeout' => 30,
        'max_retries' => 3,
        'context' => [
            'user_id' => null,
        ],
    ],

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

    'features' => [
        'auto_suspend_io' => true,
        'enable_monitoring' => true,
        'strict_destruct_check' => true,
    ],

    'scheduler' => [
        'driver' => 'local',
        'local' => [
            'size' => \\Nova\\Fibers\\Support\\CpuInfo::get() * 2,
            'max_exec_time' => 60,
        ],
        'distributed' => [
            'nodes' => [],
            'port' => 8000,
        ],
    ],

    'profiler' => [
        'enabled' => false,
        'web_panel' => [
            'enabled' => false,
            'host' => 'localhost',
            'port' => 8080,
        ],
    ],
];
PHP;
    }
}


