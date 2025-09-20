<?php

namespace Nova\Fibers\Support;

/**
 * Config - 配置管理类
 * 
 * 提供配置文件的加载和管理功能
 */
class Config
{
    /**
     * @var array 配置数据
     */
    private static array $config = [];

    /**
     * @var string 配置文件路径
     */
    private static string $configPath = '';

    /**
     * 加载配置文件
     *
     * @param string $configPath 配置文件路径
     * @return void
     */
    public static function load(string $configPath): void
    {
        self::$configPath = $configPath;
        
        if (file_exists($configPath)) {
            self::$config = require $configPath;
        }
    }

    /**
     * 获取配置值
     *
     * @param string $key 配置键，支持点号分隔的嵌套键
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public static function get(string $key, $default = null)
    {
        if (empty(self::$config)) {
            // 如果配置未加载，尝试自动加载
            self::autoLoad();
        }

        $keys = explode('.', $key);
        $config = self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                return $default;
            }
            $config = $config[$k];
        }

        return $config;
    }

    /**
     * 设置配置值
     *
     * @param string $key 配置键，支持点号分隔的嵌套键
     * @param mixed $value 配置值
     * @return void
     */
    public static function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * 检查配置是否存在
     *
     * @param string $key 配置键
     * @return bool 是否存在
     */
    public static function has(string $key): bool
    {
        return self::get($key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }

    /**
     * 获取所有配置
     *
     * @return array 所有配置
     */
    public static function all(): array
    {
        if (empty(self::$config)) {
            // 如果配置未加载，尝试自动加载
            self::autoLoad();
        }

        return self::$config;
    }

    /**
     * 自动加载配置文件
     *
     * @return void
     */
    private static function autoLoad(): void
    {
        // 尝试从常见的配置目录加载
        $possiblePaths = [
            __DIR__ . '/../../config/fibers.php',
            __DIR__ . '/../../../config/fibers.php',
            getcwd() . '/config/fibers.php',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                self::load($path);
                return;
            }
        }
    }
}