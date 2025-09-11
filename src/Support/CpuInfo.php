<?php

declare(strict_types=1);

namespace Nova\Fibers\Support;

/**
 * CPU 信息获取工具类
 *
 * @package Nova\Fibers\Support
 */
class CpuInfo
{
    /**
     * 获取 CPU 核心数
     *
     * @return int CPU 核心数
     */
    public static function get(): int
    {
        // 检查缓存
        static $cpuCount = null;

        if ($cpuCount !== null) {
            return $cpuCount;
        }

        // 尝试不同方法获取 CPU 核心数
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            // Windows 系统
            $cpuCount = self::getCpuCountWindows();
        } else {
            // Unix/Linux/macOS 系统
            $cpuCount = self::getCpuCountUnix();
        }

        // 确保返回值至少为 1
        $cpuCount = max(1, $cpuCount);

        return $cpuCount;
    }

    /**
     * 获取 Windows 系统的 CPU 核心数
     *
     * @return int CPU 核心数
     */
    protected static function getCpuCountWindows(): int
    {
        // 尝试通过环境变量获取
        if (($num = getenv('NUMBER_OF_PROCESSORS')) !== false) {
            return (int)$num;
        }

        // 尝试通过系统命令获取
        try {
            $process = @popen('echo %NUMBER_OF_PROCESSORS%', 'rb');
            if ($process !== false) {
                $output = @fread($process, 2096);
                @pclose($process);

                if ($output !== false && trim($output) !== '') {
                    return (int)trim($output);
                }
            }
        } catch (\Throwable $e) {
            // 忽略异常
        }

        // 默认返回 1
        return 1;
    }

    /**
     * 获取 Unix/Linux/macOS 系统的 CPU 核心数
     *
     * @return int CPU 核心数
     */
    protected static function getCpuCountUnix(): int
    {
        // 尝试使用 nproc 命令
        try {
            $output = @shell_exec('nproc 2>/dev/null');
            if ($output !== null && trim($output) !== '') {
                return (int)trim($output);
            }
        } catch (\Throwable $e) {
            // 忽略异常
        }

        // 尝试使用 sysctl 命令 (macOS/BSD)
        try {
            $output = @shell_exec('sysctl -n hw.ncpu 2>/dev/null');
            if ($output !== null && trim($output) !== '') {
                return (int)trim($output);
            }
        } catch (\Throwable $e) {
            // 忽略异常
        }

        // 尝试读取 /proc/cpuinfo (Linux)
        try {
            if (is_readable('/proc/cpuinfo')) {
                $cpuInfo = file_get_contents('/proc/cpuinfo');
                preg_match_all('/^processor/m', $cpuInfo, $matches);
                $count = count($matches[0]);

                if ($count > 0) {
                    return $count;
                }
            }
        } catch (\Throwable $e) {
            // 忽略异常
        }

        // 尝试使用 PHP 内置函数
        $count = defined('PHP_CLI_PROCESSORS_COUNT') ? PHP_CLI_PROCESSORS_COUNT : 0;
        if ($count > 0) {
            return $count;
        }

        // 默认返回 1
        return 1;
    }
}
