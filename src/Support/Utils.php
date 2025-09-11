<?php

declare(strict_types=1);

namespace Nova\Fibers\Support;

/**
 * 工具类
 * 
 * 提供各种辅助方法
 */
class Utils
{
    /**
     * 生成唯一的ID
     * 
     * @return string 唯一ID
     */
    public static function generateId(): string
    {
        return uniqid(sprintf('%s_', substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 4)), true);
    }

    /**
     * 检查是否是关联数组
     * 
     * @param array $array 数组
     * @return bool 是否是关联数组
     */
    public static function isAssociativeArray(array $array): bool
    {
        if ([] === $array) {
            return false;
        }
        
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * 递归合并数组
     * 
     * @param array $array1 数组1
     * @param array $array2 数组2
     * @return array 合并后的数组
     */
    public static function mergeArrays(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = self::mergeArrays($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        
        return $array1;
    }

    /**
     * 获取数组中的值，支持点符号访问
     * 
     * @param array $array 数组
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed 值
     */
    public static function arrayGet(array $array, string $key, mixed $default = null): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }
        
        // 支持点符号访问
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            
            $array = $array[$segment];
        }
        
        return $array;
    }

    /**
     * 设置数组中的值，支持点符号访问
     * 
     * @param array $array 数组
     * @param string $key 键名
     * @param mixed $value 值
     * @return array 修改后的数组
     */
    public static function arraySet(array &$array, string $key, mixed $value): array
    {
        $keys = explode('.', $key);
        
        while (count($keys) > 1) {
            $key = array_shift($keys);
            
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            
            $array = &$array[$key];
        }
        
        $array[array_shift($keys)] = $value;
        
        return $array;
    }
}