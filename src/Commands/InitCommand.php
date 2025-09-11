<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 初始化命令类
 * 
 * 用于生成框架配置文件
 */
class InitCommand
{
    /**
     * 执行初始化命令
     * 
     * @param array $options 命令选项
     * @return void
     */
    public function handle(array $options = []): void
    {
        // 检测当前运行环境
        if ($this->isLaravel()) {
            $this->generateLaravelConfig();
        } elseif ($this->isSymfony()) {
            $this->generateSymfonyConfig();
        } elseif ($this->isYii()) {
            $this->generateYiiConfig();
        } elseif ($this->isThinkPHP()) {
            $this->generateThinkPHPConfig();
        } else {
            $this->generateGenericConfig();
        }
    }

    /**
     * 检测是否为Laravel环境
     * 
     * @return bool 是否为Laravel环境
     */
    private function isLaravel(): bool
    {
        return class_exists(\Illuminate\Foundation\Application::class);
    }

    /**
     * 检测是否为Symfony环境
     * 
     * @return bool 是否为Symfony环境
     */
    private function isSymfony(): bool
    {
        return class_exists(\Symfony\Component\HttpKernel\Kernel::class);
    }

    /**
     * 检测是否为Yii环境
     * 
     * @return bool 是否为Yii环境
     */
    private function isYii(): bool
    {
        return class_exists(\Yii::class);
    }

    /**
     * 检测是否为ThinkPHP环境
     * 
     * @return bool 是否为ThinkPHP环境
     */
    private function isThinkPHP(): bool
    {
        return defined('THINK_VERSION');
    }

    /**
     * 生成Laravel配置文件
     * 
     * @return void
     */
    private function generateLaravelConfig(): void
    {
        $configPath = getcwd() . '/config/fibers.php';
        
        if (file_exists($configPath)) {
            echo "Configuration file already exists: {$configPath}\n";
            return;
        }
        
        $configContent = <<<'EOT'
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fiber Pool Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the default fiber pool settings.
    |
    */

    'default_pool' => [
        'size' => env('FIBER_POOL_SIZE', \Nova\Fibers\Support\CpuInfo::get() * 4),
        'timeout' => 30,
        'max_retries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the channels used by your application.
    |
    */

    'channels' => [
        'default' => [
            'buffer_size' => 100,
        ],
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
    ],
];
EOT;
        
        if (!is_dir(dirname($configPath))) {
            mkdir(dirname($configPath), 0755, true);
        }
        
        file_put_contents($configPath, $configContent);
        echo "Laravel configuration file created: {$configPath}\n";
    }

    /**
     * 生成Symfony配置文件
     * 
     * @return void
     */
    private function generateSymfonyConfig(): void
    {
        $configPath = getcwd() . '/config/packages/fibers.yaml';
        
        if (file_exists($configPath)) {
            echo "Configuration file already exists: {$configPath}\n";
            return;
        }
        
        $configContent = <<<'EOT'
# Fiber Configuration
nova_fibers:
    default_pool:
        size: '%env(int:FIBER_POOL_SIZE)%'
        timeout: 30
        max_retries: 3

    channels:
        default:
            buffer_size: 100

    features:
        auto_suspend_io: true
        enable_monitoring: true
EOT;
        
        if (!is_dir(dirname($configPath))) {
            mkdir(dirname($configPath), 0755, true);
        }
        
        file_put_contents($configPath, $configContent);
        echo "Symfony configuration file created: {$configPath}\n";
    }

    /**
     * 生成Yii配置文件
     * 
     * @return void
     */
    private function generateYiiConfig(): void
    {
        $configPath = getcwd() . '/config/fibers.php';
        
        if (file_exists($configPath)) {
            echo "Configuration file already exists: {$configPath}\n";
            return;
        }
        
        $configContent = <<<'EOT'
<?php

return [
    'components' => [
        'fiber' => [
            'class' => \Nova\Fibers\Core\FiberPool::class,
            'size' => $_ENV['FIBER_POOL_SIZE'] ?? \Nova\Fibers\Support\CpuInfo::get() * 4,
            'timeout' => 30,
            'maxRetries' => 3,
        ],
    ],
    'container' => [
        'definitions' => [
            \Nova\Fibers\Channel\Channel::class => [
                'default' => [
                    'bufferSize' => 100,
                ],
            ],
        ],
    ],
];
EOT;
        
        if (!is_dir(dirname($configPath))) {
            mkdir(dirname($configPath), 0755, true);
        }
        
        file_put_contents($configPath, $configContent);
        echo "Yii configuration file created: {$configPath}\n";
    }

    /**
     * 生成ThinkPHP配置文件
     * 
     * @return void
     */
    private function generateThinkPHPConfig(): void
    {
        $configPath = getcwd() . '/config/fibers.php';
        
        if (file_exists($configPath)) {
            echo "Configuration file already exists: {$configPath}\n";
            return;
        }
        
        $configContent = <<<'EOT'
<?php

return [
    // Fiber Pool Configuration
    'fiber_pool' => [
        'size' => $_ENV['FIBER_POOL_SIZE'] ?? \Nova\Fibers\Support\CpuInfo::get() * 4,
        'timeout' => 30,
        'max_retries' => 3,
    ],

    // Channels Configuration
    'channels' => [
        'default' => [
            'buffer_size' => 100,
        ],
    ],

    // Features Configuration
    'features' => [
        'auto_suspend_io' => true,
        'enable_monitoring' => true,
    ],
];
EOT;
        
        if (!is_dir(dirname($configPath))) {
            mkdir(dirname($configPath), 0755, true);
        }
        
        file_put_contents($configPath, $configContent);
        echo "ThinkPHP configuration file created: {$configPath}\n";
    }

    /**
     * 生成通用配置文件
     * 
     * @return void
     */
    private function generateGenericConfig(): void
    {
        $configPath = getcwd() . '/fibers-config.php';
        
        if (file_exists($configPath)) {
            echo "Configuration file already exists: {$configPath}\n";
            return;
        }
        
        $configContent = <<<'EOT'
<?php

return [
    'default_pool' => [
        'size' => \Nova\Fibers\Support\CpuInfo::get() * 4,
        'timeout' => 30,
        'max_retries' => 3,
    ],

    'channels' => [
        'default' => [
            'buffer_size' => 100,
        ],
    ],

    'features' => [
        'auto_suspend_io' => true,
        'enable_monitoring' => true,
    ],
];
EOT;
        
        file_put_contents($configPath, $configContent);
        echo "Generic configuration file created: {$configPath}\n";
    }
}
