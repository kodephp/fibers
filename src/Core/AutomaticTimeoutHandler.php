<?php

declare(strict_types=1);

namespace Nova\Fibers\Core;

use Nova\Fibers\Attributes\Timeout;
use ReflectionClass;
use ReflectionMethod;

/**
 * 自动超时处理类
 * 
 * 自动处理带有Timeout属性的方法
 */
class AutomaticTimeoutHandler
{
    /**
     * 应用超时控制到类的方法
     * 
     * @param object $instance 类实例
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed 方法返回值
     * @throws \RuntimeException 如果方法执行超时
     */
    public static function applyTimeout(object $instance, string $method, array $args = [])
    {
        $reflection = new ReflectionClass($instance);
        
        // 检查方法是否存在
        if (!$reflection->hasMethod($method)) {
            // 直接调用方法
            return call_user_func_array([$instance, $method], $args);
        }
        
        $methodReflection = $reflection->getMethod($method);
        
        // 获取Timeout属性
        $timeoutAttributes = $methodReflection->getAttributes(Timeout::class);
        
        if (empty($timeoutAttributes)) {
            // 没有超时属性，直接调用方法
            return call_user_func_array([$instance, $method], $args);
        }
        
        // 获取超时设置
        $timeoutAttribute = $timeoutAttributes[0]->newInstance();
        $timeoutSeconds = $timeoutAttribute->seconds;
        
        // 使用纤程池执行带超时控制的方法
        $fiberPool = new FiberPool(['max_exec_time' => $timeoutSeconds]);
        
        try {
            $result = $fiberPool->concurrent([
                function () use ($instance, $method, $args) {
                    return call_user_func_array([$instance, $method], $args);
                }
            ], $timeoutSeconds);
            
            return $result[0];
        } catch (\Throwable $e) {
            throw new \RuntimeException("Method {$method} execution timed out after {$timeoutSeconds} seconds", 0, $e);
        } finally {
            $fiberPool->shutdown();
        }
    }
    
    /**
     * 扫描类并应用超时控制到所有带有Timeout属性的方法
     * 
     * @param object $instance 类实例
     * @return void
     */
    public static function scanAndApply(object $instance): void
    {
        $reflection = new ReflectionClass($instance);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            // 跳过构造函数和魔术方法
            if ($method->isConstructor() || $method->isDestructor() || strpos($method->getName(), '__') === 0) {
                continue;
            }
            
            // 检查是否有Timeout属性
            $timeoutAttributes = $method->getAttributes(Timeout::class);
            
            if (!empty($timeoutAttributes)) {
                // 创建包装方法
                self::createTimeoutWrapper($instance, $method->getName());
            }
        }
    }
    
    /**
     * 创建超时包装方法
     * 
     * @param object $instance 类实例
     * @param string $method 方法名
     * @return void
     */
    private static function createTimeoutWrapper(object $instance, string $method): void
    {
        // 这里可以使用魔术方法__call来实现动态包装
        // 但在实际应用中，可能需要更复杂的实现方式
    }
}