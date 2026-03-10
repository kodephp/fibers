<?php

declare(strict_types=1);

namespace Kode\Fibers\Providers;

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Fibers;

/**
 * 通用服务提供者
 * 适用于默认框架或普通PHP项目
 */
class GenericProvider
{
    /**
     * 容器实例
     *
     * @var array
     */
    protected static array $container = [];
    
    /**
     * 是否已初始化
     *
     * @var bool
     */
    protected static bool $initialized = false;
    
    /**
     * 初始化服务提供者
     *
     * @param array $config
     * @return void
     */
    public static function init(array $config = []): void
    {
        if (self::$initialized) {
            return;
        }
        
        // 初始化容器
        self::registerServices($config);
        
        self::$initialized = true;
    }
    
    /**
     * 注册服务
     *
     * @param array $config
     * @return void
     */
    protected static function registerServices(array $config): void
    {
        // 注册FiberPool服务
        self::$container[FiberPool::class] = static function () use ($config) {
            $poolConfig = $config['default_pool'] ?? [];
            return new FiberPool($poolConfig);
        };
        
        // 注册Fibers服务
        self::$container[Fibers::class] = static function () {
            return new Fibers();
        };
        
        // 注册Channels
        self::$container['fibers.channels'] = static function () use ($config) {
            $channels = [];
            $channelConfigs = $config['channels'] ?? [];
            
            foreach ($channelConfigs as $name => $channelConfig) {
                $bufferSize = $channelConfig['buffer_size'] ?? 0;
                $channels[$name] = Channel::make($name, $bufferSize);
            }
            
            return $channels;
        };
    }
    
    /**
     * 获取服务实例
     *
     * @template T
     * @param class-string<T> $name
     * @return T
     */
    public static function get(string $name)
    {
        if (!isset(self::$container[$name])) {
            throw new \RuntimeException("Service not found: {$name}");
        }
        
        $service = self::$container[$name];
        
        // 如果是工厂函数，执行它
        if (is_callable($service)) {
            $service = $service();
            // 缓存实例
            self::$container[$name] = $service;
        }
        
        return $service;
    }
    
    /**
     * 注册自定义服务
     *
     * @param string $name
     * @param mixed $service
     * @return void
     */
    public static function register(string $name, mixed $service): void
    {
        self::$container[$name] = $service;
    }
    
    /**
     * 检查服务是否存在
     *
     * @param string $name
     * @return bool
     */
    public static function has(string $name): bool
    {
        return isset(self::$container[$name]);
    }
    
    /**
     * 清除服务实例
     *
     * @param string $name
     * @return void
     */
    public static function reset(string $name): void
    {
        if (isset(self::$container[$name])) {
            unset(self::$container[$name]);
        }
    }
    
    /**
     * 清除所有服务实例
     *
     * @return void
     */
    public static function resetAll(): void
    {
        self::$container = [];
        self::$initialized = false;
    }
}