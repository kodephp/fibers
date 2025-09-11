<?php

declare(strict_types=1);

namespace Nova\Fibers\Support;

use Nova\Fibers\Support\Utils;

/**
 * 配置管理类
 * 
 * 用于加载和管理配置文件
 */
class Config
{
    /**
     * 配置数据
     * 
     * @var array
     */
    private array $config = [];

    /**
     * 构造函数
     * 
     * @param array|string $config 配置数组或配置文件路径
     */
    public function __construct(array|string $config = [])
    {
        if (is_string($config)) {
            $this->loadFromFile($config);
        } else {
            $this->config = $config;
        }
    }

    /**
     * 从文件加载配置
     * 
     * @param string $filePath 配置文件路径
     * @return void
     */
    public function loadFromFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Config file not found: {$filePath}");
        }
        
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        switch ($extension) {
            case 'php':
                $this->config = require $filePath;
                break;
            case 'json':
                $content = file_get_contents($filePath);
                $this->config = json_decode($content, true);
                break;
            case 'yaml':
            case 'yml':
                if (!function_exists('yaml_parse')) {
                    throw new \RuntimeException('YAML extension is required to parse YAML config files');
                }
                $content = file_get_contents($filePath);
                $this->config = yaml_parse($content);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported config file format: {$extension}");
        }
    }

    /**
     * 获取配置值
     * 
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Utils::arrayGet($this->config, $key, $default);
    }

    /**
     * 设置配置值
     * 
     * @param string $key 配置键名
     * @param mixed $value 配置值
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        Utils::arraySet($this->config, $key, $value);
    }

    /**
     * 检查配置是否存在
     * 
     * @param string $key 配置键名
     * @return bool 是否存在
     */
    public function has(string $key): bool
    {
        return Utils::arrayGet($this->config, $key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }

    /**
     * 获取所有配置
     * 
     * @return array 配置数组
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * 合并配置
     * 
     * @param array $config 要合并的配置
     * @return void
     */
    public function merge(array $config): void
    {
        $this->config = Utils::mergeArrays($this->config, $config);
    }
}