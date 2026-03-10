<?php

declare(strict_types=1);

namespace Kode\Fibers\Support;

class Environment
{
    /**
     * 检查环境是否满足要求
     *
     * @return void
     * @throws \RuntimeException 如果环境不满足要求
     */
    public static function check(): void
    {
        // 检查PHP版本
        if (version_compare(PHP_VERSION, '8.1.0') < 0) {
            throw new \RuntimeException('PHP version must be 8.1 or higher');
        }
    }

    /**
     * 诊断环境问题
     *
     * @return array 问题列表
     */
    public static function diagnose(): array
    {
        $issues = [];

        // 检查PHP版本
        if (version_compare(PHP_VERSION, '8.1.0') < 0) {
            $issues[] = [
                'type' => 'php_version',
                'message' => 'PHP version must be 8.1 or higher',
                'recommendation' => 'Upgrade your PHP version to 8.1 or higher'
            ];
        }

        // 检查析构函数中的Fiber限制
        if (PHP_VERSION_ID < 80400) {
            $issues[] = [
                'type' => 'fiber_unsafe',
                'message' => 'PHP < 8.4 does not allow Fiber::suspend() in __destruct()',
                'recommendation' => 'Use safe destruct mode or upgrade to PHP 8.4+'
            ];
        }

        // 检查禁用函数
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        $disabledFunctions = array_map('trim', $disabledFunctions);

        $requiredFunctions = [
            'pcntl_fork' => 'Required for process management',
            'proc_open' => 'Required for process execution',
            'exec' => 'Required for command execution'
        ];

        foreach ($requiredFunctions as $function => $description) {
            if (in_array($function, $disabledFunctions)) {
                $issues[] = [
                    'type' => 'function_disabled',
                    'message' => "{$function} is disabled",
                    'recommendation' => "Enable {$function} or use alternative methods"
                ];
            }
        }

        // 检查必要的扩展
        $requiredExtensions = [
            'sockets' => 'Required for socket operations',
            'pcntl' => 'Required for process control',
            'posix' => 'Required for POSIX functions'
        ];

        foreach ($requiredExtensions as $extension => $description) {
            if (!extension_loaded($extension)) {
                $issues[] = [
                    'type' => 'extension_missing',
                    'message' => "{$extension} extension is not installed",
                    'recommendation' => "Install the {$extension} extension"
                ];
            }
        }

        return $issues;
    }
}