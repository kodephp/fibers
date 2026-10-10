<?php

declare(strict_types=1);

namespace Kode\Fibers\Support;

/**
 * 运行环境检测与诊断工具
 *
 * 负责 PHP 版本、扩展、禁用函数以及 Fiber 析构安全性的检测。
 * 自 v5.0.0 起最低要求 PHP 8.4+。
 */
class Environment
{
    /**
     * 最低支持的 PHP 版本
     */
    public const string MIN_PHP_VERSION = '8.4.0';

    /**
     * 支持析构中安全 suspend 的最低 PHP 版本 ID（PHP 8.4+）
     */
    public const int SAFE_DESTRUCT_PHP_VERSION_ID = 80400;

    /**
     * 检查环境是否满足要求
     *
     * PHP 版本由 composer.json 的 "php": "^8.4" 约束保证，
     * 此处无需再做运行时版本比较。
     *
     * @return void
     */
    public static function check(): void
    {
    }

    /**
     * 诊断环境问题
     *
     * @return array<int, array{type: string, message: string, recommendation?: string}> 问题列表
     */
    public static function diagnose(): array
    {
        $issues = [];

        // 检查禁用函数
        $disabledFunctions = explode(',', (string) ini_get('disable_functions'));
        $disabledFunctions = array_map('trim', $disabledFunctions);

        $requiredFunctions = [
            'pcntl_fork' => 'Required for process management',
            'proc_open' => 'Required for process execution',
            'exec' => 'Required for command execution',
        ];

        foreach ($requiredFunctions as $function => $description) {
            if (in_array($function, $disabledFunctions, true)) {
                $issues[] = [
                    'type' => 'function_disabled',
                    'message' => "{$function} is disabled",
                    'recommendation' => "Enable {$function} or use alternative methods. {$description}",
                ];
            }
        }

        // 检查必要的扩展
        $requiredExtensions = [
            'sockets' => 'Required for socket operations',
            'pcntl' => 'Required for process control',
            'posix' => 'Required for POSIX functions',
        ];

        foreach ($requiredExtensions as $extension => $description) {
            if (!extension_loaded($extension)) {
                $issues[] = [
                    'type' => 'extension_missing',
                    'message' => "{$extension} extension is not installed",
                    'recommendation' => "Install the {$extension} extension. {$description}",
                ];
            }
        }

        return $issues;
    }

    /**
     * 检查是否支持析构函数中安全使用 Fiber
     *
     * PHP 8.4+ 恒为 true，保留以兼容旧调用方。
     *
     * @return bool
     */
    public static function supportsDestructInFiber(): bool
    {
        return true;
    }

    /**
     * 检查指定的禁用函数列表
     *
     * @param array<int, string> $functions 函数名列表
     * @return bool 只要有一个被禁用就返回 true
     */
    public static function hasDisabledFunctions(array $functions): bool
    {
        $disabledFunctions = explode(',', (string) ini_get('disable_functions'));
        $disabledFunctions = array_map('trim', $disabledFunctions);

        foreach ($functions as $function) {
            if (in_array($function, $disabledFunctions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查必要的扩展是否安装
     *
     * @param array<int, string> $extensions 扩展名列表
     * @return bool 只要有一个未安装就返回 false
     */
    public static function hasRequiredExtensions(array $extensions): bool
    {
        foreach ($extensions as $extension) {
            if (!extension_loaded($extension)) {
                return false;
            }
        }

        return true;
    }
}