<?php

declare(strict_types=1);

namespace Nova\Fibers\Support;

/**
 * 运行环境检测工具类
 * 
 * 提供对运行环境的检测功能，包括PHP版本、禁用函数等，确保纤程功能正常运行
 */
class Environment
{
    /**
     * 检查PHP版本是否支持纤程功能
     * 
     * @return bool 是否支持纤程
     */
    public static function supportsFibers(): bool
    {
        return version_compare(PHP_VERSION, '8.1.0', '>=');
    }
    
    /**
     * 检查PHP版本是否支持纤程功能（兼容旧方法名）
     * 
     * @return bool 是否支持纤程
     */
    public static function checkFiberSupport(): bool
    {
        return self::supportsFibers();
    }

    /**
     * 检查是否应该启用安全析构模式
     * 
     * @return bool 是否应该启用安全析构模式
     */
    public static function shouldEnableSafeDestructMode(): bool
    {
        // 如果PHP版本小于8.4.0，则应该启用安全析构模式
        return !self::supportsFiberSuspendInDestruct();
    }

    /**
     * 检查是否在析构函数中支持纤程挂起
     * 
     * @return bool 是否支持析构函数中挂起
     */
    public static function supportsFiberSuspendInDestruct(): bool
    {
        // PHP 8.4.0及以上版本支持在析构函数中挂起纤程
        return version_compare(PHP_VERSION, '8.4.0', '>=');
    }

    /**
     * 获取禁用的函数列表
     * 
     * @return array 禁用的函数列表
     */
    public static function getDisabledFunctions(): array
    {
        $disabled = ini_get('disable_functions');
        if (empty($disabled)) {
            return [];
        }
        
        return array_map('trim', explode(',', $disabled));
    }

    /**
     * 检查特定函数是否被禁用
     * 
     * @param string $function 函数名称
     * @return bool 是否被禁用
     */
    public static function isFunctionDisabled(string $function): bool
    {
        $disabled = self::getDisabledFunctions();
        return in_array($function, $disabled, true);
    }

    /**
     * 诊断运行环境
     * 
     * @return array 诊断结果
     */
    public static function diagnose(): array
    {
        $issues = [];
        
        // 检查PHP版本
        if (!self::supportsFibers()) {
            $issues[] = [
                'type' => 'php_version',
                'message' => 'PHP version must be 8.1.0 or higher to support fibers',
                'severity' => 'error'
            ];
        }
        
        // 检查析构函数中纤程挂起支持
        if (!self::supportsFiberSuspendInDestruct()) {
            $issues[] = [
                'type' => 'fiber_destruct',
                'message' => 'Fiber suspension in destructors is not supported in PHP < 8.4.0',
                'severity' => 'warning'
            ];
        }
        
        // 检查禁用的函数
        $criticalFunctions = ['proc_open', 'exec', 'shell_exec', 'system'];
        foreach ($criticalFunctions as $function) {
            if (self::isFunctionDisabled($function)) {
                $issues[] = [
                    'type' => 'function_disabled',
                    'message' => "Function '{$function}' is disabled which may affect fiber functionality",
                    'severity' => 'warning'
                ];
            }
        }
        
        // 检查pcntl扩展（如果需要的话）
        if (!extension_loaded('pcntl') && self::supportsFibers()) {
            $issues[] = [
                'type' => 'extension_missing',
                'message' => 'PCNTL extension is not loaded, some fiber features may be limited',
                'severity' => 'notice'
            ];
        }
        
        return $issues;
    }

    /**
     * 获取环境信息
     * 
     * @return array 环境信息
     */
    public static function getInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_version_id' => PHP_VERSION_ID,
            'os' => PHP_OS,
            'fiber_supported' => self::supportsFibers(),
            'fiber_suspend_in_destruct' => self::supportsFiberSuspendInDestruct(),
            'disabled_functions' => self::getDisabledFunctions(),
            'extensions' => get_loaded_extensions(),
        ];
    }
}
