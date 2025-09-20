<?php

namespace Nova\Fibers\Support;

/**
 * CpuInfo - CPU信息类
 * 
 * 提供获取CPU核心数等信息的方法
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
        // 尝试从不同来源获取CPU核心数
        $cores = null;
        
        // Windows系统
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            // 尝试使用wmic命令获取CPU核心数
            $cmd = 'wmic cpu get NumberOfCores /value';
            $output = shell_exec($cmd);
            
            if ($output !== null) {
                // 解析输出
                if (preg_match('/NumberOfCores=(\d+)/', $output, $matches)) {
                    $cores = (int)$matches[1];
                }
            }
        } 
        // Unix/Linux/Mac系统
        else {
            // 尝试使用nproc命令
            $output = shell_exec('nproc 2>/dev/null');
            
            if ($output !== null) {
                $cores = (int)trim($output);
            } 
            // 尝试使用sysctl命令（Mac）
            elseif (PHP_OS === 'Darwin') {
                $output = shell_exec('sysctl -n hw.ncpu 2>/dev/null');
                
                if ($output !== null) {
                    $cores = (int)trim($output);
                }
            } 
            // 尝试读取/proc/cpuinfo文件（Linux）
            elseif (is_readable('/proc/cpuinfo')) {
                $cpuinfo = file_get_contents('/proc/cpuinfo');
                
                if ($cpuinfo !== false) {
                    preg_match_all('/^processor/m', $cpuinfo, $matches);
                    $cores = count($matches[0]);
                }
            }
        }
        
        // 如果无法获取CPU核心数，返回默认值
        if ($cores === null || $cores < 1) {
            // 检查是否定义了环境变量
            $envCores = getenv('CPU_CORES');
            
            if ($envCores !== false) {
                $cores = (int)$envCores;
            } else {
                // 默认返回4核心
                $cores = 4;
            }
        }
        
        return $cores;
    }
}
