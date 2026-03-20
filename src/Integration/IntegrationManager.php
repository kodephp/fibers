<?php

declare(strict_types=1);

namespace Kode\Fibers\Integration;

use Kode\Fibers\Fibers;

/**
 * 框架集成管理器
 *
 * 统一管理不同框架的集成配置
 */
class IntegrationManager
{
    protected static array $bindings = [];
    protected static array $services = [];

    /**
     * 自动初始化框架集成
     */
    public static function boot(?string $framework = null): void
    {
        $framework = $framework ?? FrameworkDetector::detect();
        
        $method = 'boot' . ucfirst(strtolower($framework));
        
        if (method_exists(self::class, $method)) {
            self::$method();
        }
        
        self::registerBaseServices();
    }

    /**
     * 注册基础服务
     */
    protected static function registerBaseServices(): void
    {
        self::$services['fibers'] = new FibersConfig();
    }

    /**
     * Laravel 集成
     */
    protected static function bootLaravel(): void
    {
        self::$bindings['app'] = '\Illuminate\Contracts\Foundation\Application';
        self::$bindings['cache'] = '\Illuminate\Contracts\Cache\Repository';
        
        if (class_exists('\Illuminate\Support\Facades\Facade')) {
            self::integrateWithFacade();
        }
    }

    /**
     * Symfony 集成
     */
    protected static function bootSymfony(): void
    {
        self::$bindings['container'] = '\Symfony\Component\DependencyInjection\ContainerInterface';
        self::$bindings['event_dispatcher'] = '\Symfony\Contracts\EventDispatcher\EventDispatcherInterface';
    }

    /**
     * Yii3 集成
     */
    protected static function bootYii3(): void
    {
        self::$bindings['di'] = '\Yiisoft\DI\Container';
        
        if (class_exists('\yii\base\Application')) {
            self::$bindings['app'] = '\yii\base\Application';
        }
    }

    /**
     * ThinkPHP 集成
     */
    protected static function bootThinkphp(): void
    {
        self::$bindings['app'] = '\think\App';
        self::$bindings['cache'] = '\think\Cache';
        
        if (function_exists('app')) {
            self::integrateWithHelper();
        }
    }

    /**
     * Hyperf 集成
     */
    protected static function bootHyperf(): void
    {
        self::$bindings['container'] = '\Hyperf\Di\Container';
        self::$bindings['server'] = '\Hyperf\Server\Server';
        
        if (class_exists('\Hyperf\Di\Aop\AspectRegister')) {
            self::integrateWithAop();
        }
    }

    /**
     * Webman 集成
     */
    protected static function bootWebman(): void
    {
        self::$bindings['app'] = '\support\App';
        self::$bindings['redis'] = '\Webman\RedisQueue\Client';
        
        if (class_exists('\Webman\MySQL\Connection')) {
            self::integrateWithDatabase();
        }
    }

    /**
     * Lumen 集成
     */
    protected static function bootLumen(): void
    {
        self::$bindings['app'] = '\Laravel\Lumen\Application';
    }

    /**
     * 基础框架集成
     */
    protected static function bootBasic(): void
    {
        self::$bindings['fibers'] = Fibers::class;
    }

    /**
     * 与 Facade 集成
     */
    protected static function integrateWithFacade(): void
    {
        self::$services['facade'] = true;
    }

    /**
     * 与助函数集成
     */
    protected static function integrateWithHelper(): void
    {
        if (!function_exists('fibers')) {
            eval('function fibers() { return \Kode\Fibers\Fibers::class; }');
        }
    }

    /**
     * 与 AOP 集成
     */
    protected static function integrateWithAop(): void
    {
        self::$services['aop'] = true;
    }

    /**
     * 与数据库集成
     */
    protected static function integrateWithDatabase(): void
    {
        self::$services['database'] = true;
    }

    /**
     * 获取服务
     */
    public static function getService(string $name): mixed
    {
        return self::$services[$name] ?? null;
    }

    /**
     * 获取绑定
     */
    public static function getBinding(string $name): ?string
    {
        return self::$bindings[$name] ?? null;
    }

    /**
     * 获取所有绑定
     */
    public static function getBindings(): array
    {
        return self::$bindings;
    }

    /**
     * 获取所有服务
     */
    public static function getServices(): array
    {
        return self::$services;
    }

    /**
     * 检查是否已集成
     */
    public static function hasIntegration(string $service): bool
    {
        return isset(self::$services[$service]);
    }

    /**
     * 创建框架服务提供者
     */
    public static function createProvider(string $framework): ?string
    {
        $providerMap = [
            'laravel' => '\Kode\Fibers\Integration\Providers\LaravelServiceProvider',
            'symfony' => '\Kode\Fibers\Integration\Providers\SymfonyBundle',
            'yii3' => '\Kode\Fibers\Integration\Providers\Yii3ServiceProvider',
            'thinkphp' => '\Kode\Fibers\Integration\Providers\ThinkPHPService',
            'hyperf' => '\Kode\Fibers\Integration\Providers\HyperfServiceProvider',
            'webman' => '\Kode\Fibers\Integration\Providers\WebmanBootstrap',
            'lumen' => '\Kode\Fibers\Integration\Providers\LumenServiceProvider',
        ];

        return $providerMap[strtolower($framework)] ?? null;
    }
}

/**
 * Fibers 配置类
 */
class FibersConfig
{
    public array $defaults = [
        'pool_size' => 16,
        'max_retries' => 3,
        'timeout' => 30,
        'enable_debug' => false,
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->defaults[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->defaults;
    }
}
