<?php

declare(strict_types=1);

namespace Nova\Fibers\Support;

/**
 * CPU信息检测工具类
 * 
 * 提供获取CPU核心数等系统信息的功能，用于动态配置纤程池大小
 */
class CpuInfo
{
    /**
     * 获取CPU核心数
     * 
     * @return int CPU核心数
     */
    public static function get(): int
    {
        // 首先尝试使用Swoole扩展获取CPU核心数
        if (function_exists('swoole_cpu_num')) {
            return swoole_cpu_num();
        }

        // 尝试从系统文件或命令获取CPU核心数
        $cpuCount = self::getCpuCountFromSystem();
        
        // 如果无法获取，返回默认值
        return $cpuCount > 0 ? $cpuCount : 8;
    }

    /**
     * 从系统获取CPU核心数
     * 
     * @return int CPU核心数
     */
    protected static function getCpuCountFromSystem(): int
    {
        // Windows系统
        if (str_starts_with(PHP_OS, 'WIN')) {
            return self::getCpuCountWindows();
        }

        // Unix/Linux系统
        return self::getCpuCountUnix();
    }

    /**
     * 获取Windows系统的CPU核心数
     * 
     * @return int CPU核心数
     */
    protected static function getCpuCountWindows(): int
    {
        // 尝试通过环境变量获取
        $number = getenv('NUMBER_OF_PROCESSORS');
        if ($number !== false && is_numeric($number)) {
            return (int) $number;
        }

        // 尝试通过系统命令获取
        try {
            $process = proc_open(
                'echo %NUMBER_OF_PROCESSORS%',
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ],
                $pipes
            );

            if (is_resource($process)) {
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                if (is_numeric($output)) {
                    return (int) $output;
                }
            }
        } catch (\Throwable $e) {
            // 忽略异常，继续尝试其他方法
        }

        return 0;
    }

    /**
     * 获取Unix/Linux系统的CPU核心数
     * 
     * @return int CPU核心数
     */
    protected static function getCpuCountUnix(): int
    {
        // 尝试使用nproc命令
        try {
            $result = shell_exec('nproc 2>/dev/null');
            if ($result !== null && is_numeric(trim($result))) {
                return (int) trim($result);
            }
        } catch (\Throwable $e) {
            // 忽略异常，继续尝试其他方法
        }

        // 尝试读取/proc/cpuinfo文件
        try {
            if (is_readable('/proc/cpuinfo')) {
                $cpuInfo = file_get_contents('/proc/cpuinfo');
                preg_match_all('/^processor/m', $cpuInfo, $matches);
                return count($matches[0]);
            }
        } catch (\Throwable $e) {
            // 忽略异常
        }

        // 尝试使用sysctl命令（macOS/BSD）
        try {
            $result = shell_exec('sysctl -n hw.ncpu 2>/dev/null');
            if ($result !== null && is_numeric(trim($result))) {
                return (int) trim($result);
            }
        } catch (\Throwable $e) {
            // 忽略异常
        }

        return 0;
    }
}
