<?php

declare(strict_types=1);

namespace Kode\Fibers\Support;

/**
 * 运行环境检测与诊断工具
 *
 * 负责 PHP 版本、扩展、禁用函数以及 Fiber 析构安全性的检测。
 * 自 v3.5.0 起最低要求 PHP 8.3+。
 */
class Environment
{
    /**
     * 最低支持的 PHP 版本
     */
    public const string MIN_PHP_VERSION = '8.3.0';

    /**
     * 支持析构中安全 suspend 的最低 PHP 版本 ID（PHP 8.4+）
     */
    public const int SAFE_DESTRUCT_PHP_VERSION_ID = 80400;

    /**
     * 检查环境是否满足要求
     *
     * @return void
     * @throws \RuntimeException 如果环境不满足要求
     */
    public static function check(): void
    {
        // 检查 PHP 版本（最低 8.3+）
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION) < 0) {
            throw new \RuntimeException(
                sprintf('PHP version must be %s or higher, current: %s', self::MIN_PHP_VERSION, PHP_VERSION)
            );
        }
    }

    /**
     * 诊断环境问题
     *
     * @return array<int, array{type: string, message: string, recommendation?: string}> 问题列表
     */
    public static function diagnose(): array
    {
        $issues = [];

        // 检查 PHP 版本
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION) < 0) {
            $issues[] = [
                'type' => 'php_version',
                'message' => sprintf('PHP version must be %s or higher, current: %s', self::MIN_PHP_VERSION, PHP_VERSION),
                'recommendation' => 'Upgrade your PHP version to 8.3 or higher',
            ];
        }

        // 检查析构函数中的 Fiber 限制（PHP < 8.4）
        if (PHP_VERSION_ID < self::SAFE_DESTRUCT_PHP_VERSION_ID) {
            $issues[] = [
                'type' => 'fiber_unsafe',
                'message' => 'PHP < 8.4 does not allow Fiber::suspend() in __destruct()',
                'recommendation' => 'Use safe destruct mode or upgrade to PHP 8.4+',
            ];
        }

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
     * @return bool
     */
    public static function supportsDestructInFiber(): bool
    {
        return PHP_VERSION_ID >= self::SAFE_DESTRUCT_PHP_VERSION_ID;
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