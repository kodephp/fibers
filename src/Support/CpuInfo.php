<?php

declare(strict_types=1);

namespace Kode\Fibers\Support;

class CpuInfo
{
    /**
     * 获取CPU核心数
     *
     * @param bool $forceRefresh 是否强制刷新缓存
     * @return int CPU核心数
     */
    public static function get(bool $forceRefresh = false): int
    {
        // 检查是否已经缓存了结果
        static $cpuCount = null;
        
        if ($cpuCount !== null && !$forceRefresh) {
            return $cpuCount;
        }
        
        // 尝试不同的方法获取CPU核心数
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows系统
            $cpuCount = self::getCpuCountWindows();
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            // macOS系统
            $cpuCount = self::getCpuCountMacOS();
        } else {
            // Linux/Unix系统
            $cpuCount = self::getCpuCountLinux();
        }
        
        // 确保返回值至少为1
        $cpuCount = max(1, $cpuCount);
        
        return $cpuCount;
    }
    
    /**
     * 获取Windows系统的CPU核心数
     *
     * @return int CPU核心数
     */
    private static function getCpuCountWindows(): int
    {
        // 尝试使用wmic命令
        if (function_exists('shell_exec')) {
            $output = shell_exec('wmic cpu get NumberOfCores 2>&1');
            if ($output !== null) {
                // 解析输出
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    if (is_numeric(trim($line))) {
                        return (int) trim($line);
                    }
                }
            }
        }
        
        // 回退到环境变量
        $numprocs = getenv('NUMBER_OF_PROCESSORS');
        if ($numprocs !== false && is_numeric($numprocs)) {
            return (int) $numprocs;
        }
        
        // 默认返回4
        return 4;
    }
    
    /**
     * 获取macOS系统的CPU核心数
     *
     * @return int CPU核心数
     */
    private static function getCpuCountMacOS(): int
    {
        // 尝试使用sysctl命令
        if (function_exists('shell_exec')) {
            $output = shell_exec('sysctl -n hw.ncpu 2>&1');
            if ($output !== null && is_numeric(trim($output))) {
                return (int) trim($output);
            }
        }
        
        // 默认返回4
        return 4;
    }
    
    /**
     * 获取Linux系统的CPU核心数
     *
     * @return int CPU核心数
     */
    private static function getCpuCountLinux(): int
    {
        // 尝试读取/proc/cpuinfo文件
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $count = count($matches[0]);
            
            if ($count > 0) {
                return $count;
            }
        }
        
        // 尝试使用nproc命令
        if (function_exists('shell_exec')) {
            $output = shell_exec('nproc 2>&1');
            if ($output !== null && is_numeric(trim($output))) {
                return (int) trim($output);
            }
        }
        
        // 尝试使用lscpu命令
        if (function_exists('shell_exec')) {
            $output = shell_exec('lscpu 2>&1');
            if ($output !== null) {
                // 查找 "CPU(s):" 行
                if (preg_match('/^CPU\(s\):\s+(\d+)/m', $output, $matches)) {
                    return (int) $matches[1];
                }
            }
        }
        
        // 默认返回4
        return 4;
    }
    
    /**
     * 获取推荐的纤程池大小
     *
     * @param int $multiplier 乘数，默认为4
     * @param bool $forceRefresh 是否强制刷新CPU核心数缓存
     * @return int 推荐的纤程池大小
     */
    public static function getRecommendedPoolSize(int $multiplier = 4, bool $forceRefresh = false): int
    {
        $cpuCount = self::get($forceRefresh);
        // 确保乘数至少为1
        $multiplier = max(1, $multiplier);
        return $cpuCount * $multiplier;
    }
    
    /**
     * 清除CPU核心数缓存
     */
    public static function clearCache(): void
    {
        // 通过设置为null来清除静态变量缓存
        $cpuCount = null;
    }
    
    /**
     * 检测系统类型
     *
     * @return string 系统类型（Windows, Linux, Darwin, Unix等）
     */
    public static function getSystemType(): string
    {
        return PHP_OS_FAMILY;
    }
    
    /**
     * 获取系统内存信息（如果可用）
     *
     * @return array 内存信息数组
     */
    public static function getMemoryInfo(): array
    {
        $memoryInfo = [
            'total' => null,
            'free' => null,
            'used' => null,
            'unit' => 'bytes'
        ];

        // Windows系统
        if (PHP_OS_FAMILY === 'Windows') {
            if (function_exists('shell_exec')) {
                $output = shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value');
                if ($output !== null) {
                    preg_match('/TotalVisibleMemorySize=(\d+)/', $output, $totalMatches);
                    preg_match('/FreePhysicalMemory=(\d+)/', $output, $freeMatches);
                    
                    if (isset($totalMatches[1]) && isset($freeMatches[1])) {
                        // Windows返回的是KB
                        $memoryInfo['total'] = (int)$totalMatches[1] * 1024;
                        $memoryInfo['free'] = (int)$freeMatches[1] * 1024;
                        $memoryInfo['used'] = $memoryInfo['total'] - $memoryInfo['free'];
                    }
                }
            }
        }
        // Linux/Unix系统
        else if (file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            if ($meminfo !== false) {
                preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $totalMatches);
                preg_match('/MemFree:\s+(\d+)\s+kB/', $meminfo, $freeMatches);
                preg_match('/Buffers:\s+(\d+)\s+kB/', $meminfo, $bufferMatches);
                preg_match('/Cached:\s+(\d+)\s+kB/', $meminfo, $cacheMatches);
                
                if (isset($totalMatches[1]) && isset($freeMatches[1])) {
                    // Linux返回的是KB
                    $memoryInfo['total'] = (int)$totalMatches[1] * 1024;
                    $free = (int)$freeMatches[1];
                    $buffers = isset($bufferMatches[1]) ? (int)$bufferMatches[1] : 0;
                    $cached = isset($cacheMatches[1]) ? (int)$cacheMatches[1] : 0;
                    $memoryInfo['free'] = ($free + $buffers + $cached) * 1024;
                    $memoryInfo['used'] = $memoryInfo['total'] - $memoryInfo['free'];
                }
            }
        }
        // macOS
        else if (PHP_OS_FAMILY === 'Darwin' && function_exists('shell_exec')) {
            $output = shell_exec('sysctl hw.memsize');
            if ($output !== null) {
                preg_match('/hw\.memsize:\s+(\d+)/', $output, $totalMatches);
                if (isset($totalMatches[1])) {
                    $memoryInfo['total'] = (int)$totalMatches[1];
                    
                    // 获取可用内存（通过vm_stat命令）
                    $vmStatOutput = shell_exec('vm_stat');
                    if ($vmStatOutput !== null) {
                        preg_match_all('/Pages\s+[^:]+:\s+(\d+)/', $vmStatOutput, $matches);
                        if (count($matches[1]) >= 5) {
                            $freePages = (int)$matches[1][0];
                            $activePages = (int)$matches[1][1];
                            $inactivePages = (int)$matches[1][2];
                            $speculativePages = (int)$matches[1][3];
                            $wiredPages = (int)$matches[1][4];
                            
                            // 每页大小通常是4KB
                            $pageSize = 4096;
                            $memoryInfo['free'] = ($freePages + $inactivePages + $speculativePages) * $pageSize;
                            $memoryInfo['used'] = $memoryInfo['total'] - $memoryInfo['free'];
                        }
                    }
                }
            }
        }

        return $memoryInfo;
    }
    
    /**
     * 获取推荐的并发任务数，基于CPU核心数和可用内存
     *
     * @param float $memoryPerTaskMB 每个任务估计使用的内存（MB）
     * @param int $cpuMultiplier CPU核心数的乘数
     * @param bool $forceRefresh 是否强制刷新系统信息缓存
     * @return int 推荐的并发任务数
     */
    public static function getRecommendedConcurrency(int $memoryPerTaskMB = 20, int $cpuMultiplier = 4, bool $forceRefresh = false): int
    {
        // 获取CPU核心数
        $cpuCount = self::get($forceRefresh);
        $cpuBasedConcurrency = $cpuCount * $cpuMultiplier;

        // 获取内存信息
        $memoryInfo = self::getMemoryInfo();
        $memoryBasedConcurrency = PHP_INT_MAX; // 默认值

        // 如果能获取到内存信息，则计算基于内存的并发数
        if ($memoryInfo['total'] !== null && $memoryInfo['total'] > 0) {
            // 转换为MB
            $totalMemoryMB = $memoryInfo['total'] / (1024 * 1024);
            // 使用总内存的80%作为可用内存
            $availableMemoryMB = $totalMemoryMB * 0.8;
            // 计算基于内存的并发数
            $memoryBasedConcurrency = (int)($availableMemoryMB / $memoryPerTaskMB);
            // 至少为1
            $memoryBasedConcurrency = max(1, $memoryBasedConcurrency);
        }

        // 返回CPU和内存限制中的较小值
        return min($cpuBasedConcurrency, $memoryBasedConcurrency);
    }
}