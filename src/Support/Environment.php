<?php

namespace Nova\Fibers\Support;

/**
 * Environment - 环境检测类
 * 
 * 提供运行环境检测和诊断功能
 */
class Environment
{
    /**
     * 诊断运行环境
     *
     * @return array 诊断结果
     */
    public static function diagnose(): array
    {
        $issues = [];
        
        // 检查PHP版本
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $issues[] = [
                'type' => 'php_version',
                'message' => 'PHP version is too low. Required: 8.1+, Current: ' . PHP_VERSION
            ];
        }
        
        // 检查Fiber扩展支持
        if (!extension_loaded('fiber')) {
            // 注意：在PHP 8.1+中，Fiber是内置的，不需要扩展
            // 这里只是为了兼容性检查
            $issues[] = [
                'type' => 'fiber_support',
                'message' => 'Fiber support is not available'
            ];
        }
        
        // 检查禁用的函数
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        $disabledFunctions = array_map('trim', $disabledFunctions);
        
        $requiredFunctions = [
            'shell_exec',
            'proc_open',
            'exec'
        ];
        
        foreach ($requiredFunctions as $function) {
            if (in_array($function, $disabledFunctions)) {
                $issues[] = [
                    'type' => 'function_disabled',
                    'message' => "Function {$function} is disabled"
                ];
            }
        }
        
        // 检查PCNTL扩展（用于进程管理）
        if (!extension_loaded('pcntl')) {
            $issues[] = [
                'type' => 'extension_missing',
                'message' => 'PCNTL extension is not loaded'
            ];
        }
        
        // 检查POSIX扩展（用于进程管理）
        if (!extension_loaded('posix')) {
            $issues[] = [
                'type' => 'extension_missing',
                'message' => 'POSIX extension is not loaded'
            ];
        }
        
        // 检查内存限制
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit !== '-1') {
            $memoryLimitBytes = self::parseMemoryLimit($memoryLimit);
            if ($memoryLimitBytes < 128 * 1024 * 1024) { // 128MB
                $issues[] = [
                    'type' => 'memory_limit',
                    'message' => 'Memory limit is too low: ' . $memoryLimit
                ];
            }
        }
        
        return $issues;
    }
    
    /**
     * 解析内存限制值
     *
     * @param string $memoryLimit 内存限制字符串
     * @return int 字节数
     */
    private static function parseMemoryLimit(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit)-1]);
        $memoryLimit = (int)$memoryLimit;
        
        switch($last) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }
        
        return $memoryLimit;
    }
}
