<?php

declare(strict_types=1);

namespace Nova\Fibers\Support;

use RuntimeException;

/**
 * 运行环境检测工具类
 *
 * @package Nova\Fibers\Support
 */
class Environment
{
    /**
     * 检查 Fiber 支持
     *
     * @return bool 是否支持 Fiber
     */
    public static function checkFiberSupport(): bool
    {
        return version_compare(PHP_VERSION, '8.1.0', '>=');
    }

    /**
     * 诊断运行环境
     *
     * @return array 问题列表
     */
    public static function diagnose(): array
    {
        $issues = [];

        // 检查 PHP 版本
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $issues[] = [
                'type' => 'php_version',
                'message' => 'PHP 8.1+ is required for Fiber support. Current version: ' . PHP_VERSION
            ];
        }

        // 检查禁用函数
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        $disabledFunctions = array_map('trim', $disabledFunctions);

        $criticalFunctions = ['pcntl_fork', 'proc_open', 'exec', 'shell_exec'];
        foreach ($criticalFunctions as $function) {
            if (in_array($function, $disabledFunctions)) {
                $issues[] = [
                    'type' => 'function_disabled',
                    'message' => "$function is disabled which may affect fiber functionality"
                ];
            }
        }

        // 检查 Fiber 析构限制（PHP 8.4 之前）
        if (version_compare(PHP_VERSION, '8.4.0', '<')) {
            $issues[] = [
                'type' => 'fiber_unsafe',
                'message' => 'PHP < 8.4: Fiber::suspend() is not allowed in __destruct() methods'
            ];
        }

        // 检查 set_time_limit
        if (function_exists('set_time_limit') && in_array('set_time_limit', $disabledFunctions)) {
            $issues[] = [
                'type' => 'fiber_unsafe',
                'message' => 'set_time_limit is disabled which may break fiber suspension'
            ];
        }

        return $issues;
    }

    /**
     * 检查是否启用安全析构模式
     *
     * @return bool
     */
    public static function shouldEnableSafeDestructMode(): bool
    {
        // PHP 8.4 之前需要启用安全模式
        return version_compare(PHP_VERSION, '8.4.0', '<');
    }
}
