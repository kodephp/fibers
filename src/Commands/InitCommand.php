<?php

declare(strict_types=1);

namespace Kode\Fibers\Commands;

use Kode\Console\Command;
use Kode\Console\Input;
use Kode\Console\Output;
use Kode\Fibers\Support\CpuInfo;
use Kode\Fibers\Support\Environment;

/**
 * 初始化配置命令
 */
class InitCommand extends Command
{
    /**
     * 支持的框架列表
     */
    private const SUPPORTED_FRAMEWORKS = [
        'laravel' => 'Laravel',
        'symfony' => 'Symfony',
        'yii3' => 'Yii3',
        'thinkphp' => 'ThinkPHP',
        'default' => 'Default/Plain PHP'
    ];
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct('init', 'Initialize configuration file for Kode/Fibers');
    }
    
    /**
     * 执行命令
     */
    public function fire(Input $in, Output $out): int
    {
        $framework = (string) $in->arg(1, 'default');
        $force = (bool) ($in->opt('force') ?? $in->flag('f'));
        
        $out->line("Initializing Kode/Fibers configuration...");
        
        // 检查环境
        $this->checkEnvironment($out);
        
        // 验证框架
        $framework = $this->validateFramework($framework, $out);
        
        // 创建配置目录
        $configDir = $this->getConfigDirectory();
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
            $out->line("Created config directory: {$configDir}");
        }
        
        // 检查配置文件是否已存在
        $configFile = $configDir . '/fibers.php';
        if (file_exists($configFile) && !$force) {
            $out->line("Configuration file already exists at {$configFile}");
            $out->line("Use --force to overwrite the existing configuration.");
            return 1;
        }
        
        // 生成配置文件
        $configContent = $this->generateConfigContent($framework);
        file_put_contents($configFile, $configContent);
        
        $out->line("Configuration file created at {$configFile}");
        $out->line("Recommended pool size: " . (CpuInfo::get() * 4) . " fibers");
        
        // 生成框架特定配置
        $this->generateFrameworkSpecificConfig($framework, $configDir, $out);
        
        $out->line("Initialization completed successfully!");
        
        return 0;
    }
    
    /**
     * 检查运行环境
     *
     * @return void
     */
    private function checkEnvironment(Output $out): void
    {
        $issues = Environment::diagnose();
        
        if (!empty($issues)) {
            $out->line("Environment warnings:");
            foreach ($issues as $issue) {
                $out->line("  ⚠️  {$issue['message']}");
            }
            $out->line("");
        }
    }
    
    /**
     * 验证框架
     *
     * @param string $framework
     * @return string
     */
    private function validateFramework(string $framework, ?Output $out = null): string
    {
        $framework = strtolower($framework);
        
        if (!array_key_exists($framework, self::SUPPORTED_FRAMEWORKS)) {
            if ($out) {
                $out->line("Unsupported framework: {$framework}");
                $out->line("Using default framework configuration.");
            }
            return 'default';
        }
        
        if ($out) {
            $out->line("Framework: " . self::SUPPORTED_FRAMEWORKS[$framework]);
        }
        return $framework;
    }
    
    /**
     * 获取配置目录
     *
     * @return string
     */
    private function getConfigDirectory(): string
    {
        // 尝试在项目根目录下创建 config 目录
        $projectRoot = $this->findProjectRoot();
        $configDir = $projectRoot . '/config';
        
        return $configDir;
    }
    
    /**
     * 查找项目根目录
     *
     * @return string
     */
    private function findProjectRoot(): string
    {
        $currentDir = __DIR__;
        
        // 向上查找包含 composer.json 的目录
        while ($currentDir !== dirname($currentDir)) {
            if (file_exists($currentDir . '/composer.json')) {
                return $currentDir;
            }
            $currentDir = dirname($currentDir);
        }
        
        // 如果找不到，使用包的目录
        return dirname(__DIR__, 2);
    }
    
    /**
     * 生成配置文件内容
     *
     * @param string $framework
     * @return string
     */
    private function generateConfigContent(string $framework): string
    {
        $cpuCount = CpuInfo::get();
        $frameworkName = self::SUPPORTED_FRAMEWORKS[$framework];
        $providerClass = $this->getProviderClass($framework);
        
        return <<<PHP
<?php

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
        'strict_destruct_check' => version_compare(PHP_VERSION, '8.4.0', '<'),
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
        'name' => env('APP_FRAMEWORK', '{$framework}'),
        'service_provider' => true,
        'provider_class' => env('FIBER_PROVIDER_CLASS', '{$providerClass}'),
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
        'cpu_cores' => {$cpuCount},
        'detected_framework' => '{$frameworkName}',
    ],
];
PHP;
    }
    
    /**
     * 获取框架的服务提供者类名
     *
     * @param string $framework
     * @return string
     */
    private function getProviderClass(string $framework): string
    {
        switch ($framework) {
            case 'laravel':
                return 'Kode\\Fibers\\Providers\\LaravelServiceProvider';
            case 'symfony':
                return 'Kode\\Fibers\\Providers\\SymfonyBundle';
            case 'yii3':
                return 'Kode\\Fibers\\Providers\\Yii3ServiceProvider';
            case 'thinkphp':
                return 'Kode\\Fibers\\Providers\\ThinkPHPService';
            default:
                return 'Kode\\Fibers\\Providers\\GenericProvider';
        }
    }
    
    /**
     * 生成框架特定配置
     *
     * @param string $framework
     * @param string $configDir
     * @param Output $out
     * @return void
     */
    private function generateFrameworkSpecificConfig(string $framework, string $configDir, Output $out): void
    {
        switch ($framework) {
            case 'laravel':
                $this->generateLaravelConfig($configDir, $out);
                break;
            case 'symfony':
                $this->generateSymfonyConfig($configDir, $out);
                break;
            // 其他框架可以在这里添加特定配置
        }
    }
    
    /**
     * 生成 Laravel 特定配置
     *
     * @param string $configDir
     * @param Output $out
     * @return void
     */
    private function generateLaravelConfig(string $configDir, Output $out): void
    {
        $laravelConfigFile = $configDir . '/fibers.php';
        $content = file_get_contents($laravelConfigFile);
        
        // 添加 Laravel 特定的配置选项
        $laravelSpecificConfig = "
    /*
    |--------------------------------------------------------------------------
    | Laravel Integration
    |--------------------------------------------------------------------------
    |
    | Laravel specific configuration options.
    |
    */
    
    'laravel' => [
        'middleware' => [
            'enable_fibers' => true,
            'timeout_middleware' => true,
        ],
        'facades' => [
            'Fibers' => 'Kode\\Fibers\\Facades\\Fibers',
        ],
        'commands' => [
            'Kode\\Fibers\\Commands\\FibersCommand',
        ],
    ]";
        
        // 在框架配置之前插入 Laravel 特定配置
        $content = str_replace(
            "'framework' => [",
            $laravelSpecificConfig . "\n\n    'framework' => [",
            $content
        );
        
        file_put_contents($laravelConfigFile, $content);
        $out->line("Added Laravel specific configuration options.");
    }
    
    /**
     * 生成 Symfony 特定配置
     *
     * @param string $configDir
     * @param Output $out
     * @return void
     */
    private function generateSymfonyConfig(string $configDir, Output $out): void
    {
        $symfonyConfigFile = $configDir . '/fibers.yaml';
        $content = <<<YAML
# Kode/Fibers Symfony Configuration

kode_fibers:
    default_pool:
        size: '%env(int:FIBER_POOL_SIZE)%'
        max_exec_time: 30
        gc_interval: 100
    
    channels: []
    
    features:
        auto_suspend_io: true
        enable_monitoring: true
        strict_destruct_check: true
    
    framework:
        name: 'symfony'
        service_provider: true
YAML;
        
        file_put_contents($symfonyConfigFile, $content);
        $out->line("Created Symfony specific configuration file: fibers.yaml");
    }
}
