<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

use Nova\Fibers\Support\CpuInfo;

/**
 * 初始化配置命令
 *
 * @package Nova\Fibers\Commands
 */
class InitCommand extends Command
{
    /**
     * InitCommand 构造函数
     */
    public function __construct()
    {
        $this->setName('init')
             ->setDescription('Initialize fibers configuration');
    }

    /**
     * 执行命令
     *
     * @param array $input 输入参数
     * @return int 退出码
     */
    public function handle(array $input = []): int
    {
        echo "Initializing Nova Fibers configuration...\n";

        // 检查是否在特定框架中
        if ($this->isLaravel()) {
            echo "Laravel detected. Publishing config file...\n";
            $this->publishLaravelConfig();
        } elseif ($this->isSymfony()) {
            echo "Symfony detected. Creating config file...\n";
            $this->createSymfonyConfig();
        } elseif ($this->isYii()) {
            echo "Yii detected. Creating config file...\n";
            $this->createYiiConfig();
        } elseif ($this->isThinkPHP()) {
            echo "ThinkPHP detected. Creating config file...\n";
            $this->createThinkPHPConfig();
        } else {
            echo "No framework detected. Creating default config file...\n";
            $this->createDefaultConfig();
        }

        echo "Configuration initialized successfully!\n";
        return 0;
    }

    /**
     * 发布 Laravel 配置文件
     *
     * @return void
     */
    protected function publishLaravelConfig(): void
    {
        // 在实际 Laravel 项目中，这会通过 Artisan 命令实现
        $this->createDefaultConfig();
        echo "Run 'php artisan vendor:publish --tag=fibers-config' to publish the config file.\n";
    }

    /**
     * 创建 Symfony 配置文件
     *
     * @return void
     */
    protected function createSymfonyConfig(): void
    {
        $config = $this->generateConfigContent();
        $configPath = 'config/packages/fibers.yaml';

        // 确保目录存在
        $dir = dirname($configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($configPath, $config);
        echo "Symfony config file created at: {$configPath}\n";
    }

    /**
     * 创建 Yii 配置文件
     *
     * @return void
     */
    protected function createYiiConfig(): void
    {
        $config = $this->generateConfigContent();
        $configPath = 'config/fibers.php';

        // 确保目录存在
        $dir = dirname($configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($configPath, "<?php\n\nreturn " . $config . ";\n");
        echo "Yii config file created at: {$configPath}\n";
    }

    /**
     * 创建 ThinkPHP 配置文件
     *
     * @return void
     */
    protected function createThinkPHPConfig(): void
    {
        $config = $this->generateConfigContent();
        $configPath = 'config/fibers.php';

        // 确保目录存在
        $dir = dirname($configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($configPath, "<?php\n\nreturn " . $config . ";\n");
        echo "ThinkPHP config file created at: {$configPath}\n";
    }

    /**
     * 创建默认配置文件
     *
     * @return void
     */
    protected function createDefaultConfig(): void
    {
        $config = $this->generateConfigContent();
        $configPath = 'config/fibers.php';

        // 确保目录存在
        $dir = dirname($configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($configPath, "<?php\n\nreturn " . $config . ";\n");
        echo "Default config file created at: {$configPath}\n";
    }

    /**
     * 生成配置内容
     *
     * @return string 配置内容
     */
    protected function generateConfigContent(): string
    {
        $cpuCount = CpuInfo::get();
        $defaultPoolSize = $cpuCount * 4;

        return "[\n" .
            "    'default_pool' => [\n" .
            "        'size' => {$defaultPoolSize},\n" .
            "        'timeout' => 30,\n" .
            "        'max_retries' => 3,\n" .
            "        'context' => ['user_id' => null]\n" .
            "    ],\n" .
            "    'channels' => [\n" .
            "        'orders' => ['buffer_size' => 100],\n" .
            "        'logs' => ['buffer_size' => 50]\n" .
            "    ],\n" .
            "    'features' => [\n" .
            "        'auto_suspend_io' => true,\n" .
            "        'enable_monitoring' => true\n" .
            "    ]\n" .
            "]";
    }
}
