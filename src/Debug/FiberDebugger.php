<?php

declare(strict_types=1);

namespace Kode\Fibers\Debug;

use Fiber;
use Kode\Fibers\Core\FiberPool;

/**
 * 纤程调试器
 *
 * 提供纤程状态监控、调试信息输出等功能。
 */
class FiberDebugger
{
    /**
     * 调试信息
     */
    protected static array $debugInfo = [];

    /**
     * 是否启用调试模式
     */
    protected static bool $enabled = false;

    /**
     * 断点列表
     */
    protected static array $breakpoints = [];

    /**
     * 启用调试模式
     *
     * @return void
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * 禁用调试模式
     *
     * @return void
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * 检查是否启用调试模式
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * 记录调试信息
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return void
     */
    public static function log(string $key, mixed $value): void
    {
        if (!self::$enabled) {
            return;
        }
        
        $fiber = Fiber::getCurrent();
        $fiberId = $fiber ? spl_object_id($fiber) : 'main';
        
        if (!isset(self::$debugInfo[$fiberId])) {
            self::$debugInfo[$fiberId] = [];
        }
        
        self::$debugInfo[$fiberId][] = [
            'key' => $key,
            'value' => $value,
            'time' => microtime(true),
            'memory' => memory_get_usage(true),
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
        ];
    }

    /**
     * 获取当前纤程信息
     *
     * @return array
     */
    public static function getCurrentFiberInfo(): array
    {
        $fiber = Fiber::getCurrent();
        
        if (!$fiber) {
            return [
                'in_fiber' => false,
                'message' => 'Not in a fiber context',
            ];
        }
        
        return [
            'in_fiber' => true,
            'fiber_id' => spl_object_id($fiber),
            'is_started' => $fiber->isStarted(),
            'is_suspended' => $fiber->isSuspended(),
            'is_terminated' => $fiber->isTerminated(),
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
        ];
    }

    /**
     * 获取所有调试信息
     *
     * @return array
     */
    public static function getDebugInfo(): array
    {
        return self::$debugInfo;
    }

    /**
     * 清除调试信息
     *
     * @return void
     */
    public static function clearDebugInfo(): void
    {
        self::$debugInfo = [];
    }

    /**
     * 设置断点
     *
     * @param string $name 断点名称
     * @param callable|null $condition 条件回调
     * @return void
     */
    public static function setBreakpoint(string $name, ?callable $condition = null): void
    {
        self::$breakpoints[$name] = [
            'condition' => $condition,
            'hit_count' => 0,
        ];
    }

    /**
     * 检查断点
     *
     * @param string $name 断点名称
     * @return bool 是否命中断点
     */
    public static function checkBreakpoint(string $name): bool
    {
        if (!isset(self::$breakpoints[$name])) {
            return false;
        }
        
        $breakpoint = &self::$breakpoints[$name];
        
        if ($breakpoint['condition'] !== null) {
            if (!($breakpoint['condition'])()) {
                return false;
            }
        }
        
        $breakpoint['hit_count']++;
        
        return true;
    }

    /**
     * 移除断点
     *
     * @param string $name 断点名称
     * @return void
     */
    public static function removeBreakpoint(string $name): void
    {
        unset(self::$breakpoints[$name]);
    }

    /**
     * 获取所有断点
     *
     * @return array
     */
    public static function getBreakpoints(): array
    {
        return self::$breakpoints;
    }

    /**
     * 转储纤程状态
     *
     * @param Fiber|null $fiber 纤程实例
     * @return array
     */
    public static function dumpFiber(?Fiber $fiber = null): array
    {
        $fiber = $fiber ?? Fiber::getCurrent();
        
        if (!$fiber) {
            return ['error' => 'No fiber to dump'];
        }
        
        return [
            'id' => spl_object_id($fiber),
            'started' => $fiber->isStarted(),
            'suspended' => $fiber->isSuspended(),
            'terminated' => $fiber->isTerminated(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];
    }

    /**
     * 转储纤程池状态
     *
     * @param FiberPool $pool 纤程池实例
     * @return array
     */
    public static function dumpPool(FiberPool $pool): array
    {
        return [
            'stats' => $pool->getStats(),
            'config' => $pool->getConfig(),
        ];
    }

    /**
     * 格式化调用栈
     *
     * @param array $trace 调用栈
     * @return string
     */
    public static function formatTrace(array $trace): string
    {
        $output = '';
        
        foreach ($trace as $i => $item) {
            $file = $item['file'] ?? 'unknown';
            $line = $item['line'] ?? 0;
            $class = $item['class'] ?? '';
            $type = $item['type'] ?? '';
            $function = $item['function'] ?? 'unknown';
            
            $output .= sprintf(
                "#%d %s(%d): %s%s%s()\n",
                $i,
                $file,
                $line,
                $class,
                $type,
                $function
            );
        }
        
        return $output;
    }

    /**
     * 生成调试报告
     *
     * @return array
     */
    public static function generateReport(): array
    {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'enabled' => self::$enabled,
            'debug_info_count' => count(self::$debugInfo),
            'breakpoints' => self::$breakpoints,
            'current_fiber' => self::getCurrentFiberInfo(),
        ];
    }

    /**
     * 输出调试信息到控制台
     *
     * @param string $message 消息
     * @param array $context 上下文
     * @return void
     */
    public static function console(string $message, array $context = []): void
    {
        if (!self::$enabled) {
            return;
        }
        
        $fiber = Fiber::getCurrent();
        $fiberId = $fiber ? spl_object_id($fiber) : 'main';
        
        $output = sprintf(
            "[Fiber:%s] %s %s",
            $fiberId,
            $message,
            empty($context) ? '' : json_encode($context, JSON_UNESCAPED_UNICODE)
        );
        
        error_log($output);
    }
}
