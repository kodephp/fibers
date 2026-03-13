<?php

declare(strict_types=1);

namespace Kode\Fibers\WebUI;

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Profiler\FiberProfiler;

/**
 * Web 管理界面
 *
 * 提供 Web UI 管理纤程池和任务的可视化界面。
 */
class WebUI
{
    /**
     * 纤程池实例
     */
    protected ?FiberPool $pool = null;

    /**
     * Profiler 实例
     */
    protected ?FiberProfiler $profiler = null;

    /**
     * 服务端口
     */
    protected int $port = 8080;

    /**
     * 服务主机
     */
    protected string $host = '0.0.0.0';

    /**
     * 是否正在运行
     */
    protected bool $running = false;

    /**
     * 创建 Web UI 实例
     *
     * @param array $options 配置选项
     */
    public function __construct(array $options = [])
    {
        if (isset($options['port'])) {
            $this->port = (int) $options['port'];
        }
        
        if (isset($options['host'])) {
            $this->host = (string) $options['host'];
        }
        
        if (isset($options['pool'])) {
            $this->pool = $options['pool'];
        }
        
        if (isset($options['profiler'])) {
            $this->profiler = $options['profiler'];
        }
    }

    /**
     * 设置纤程池
     *
     * @param FiberPool $pool 纤程池实例
     * @return self
     */
    public function setPool(FiberPool $pool): self
    {
        $this->pool = $pool;
        return $this;
    }

    /**
     * 设置 Profiler
     *
     * @param FiberProfiler $profiler Profiler 实例
     * @return self
     */
    public function setProfiler(FiberProfiler $profiler): self
    {
        $this->profiler = $profiler;
        return $this;
    }

    /**
     * 启动 Web UI 服务
     *
     * @return void
     */
    public function start(): void
    {
        $this->running = true;
        $this->handleRequest();
    }

    /**
     * 停止 Web UI 服务
     *
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * 处理 HTTP 请求
     *
     * @return void
     */
    protected function handleRequest(): void
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        header('Content-Type: application/json; charset=utf-8');
        
        $response = $this->route($path, $method);
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 路由处理
     *
     * @param string $path 请求路径
     * @param string $method 请求方法
     * @return array
     */
    protected function route(string $path, string $method): array
    {
        return match ($path) {
            '/', '/api' => $this->index(),
            '/api/status', '/api/pool/status' => $this->getPoolStatus(),
            '/api/profiler/records' => $this->getProfilerRecords(),
            '/api/profiler/stats' => $this->getProfilerStats(),
            '/api/health' => $this->healthCheck(),
            '/api/metrics' => $this->getMetrics(),
            default => ['error' => 'Not Found', 'code' => 404],
        };
    }

    /**
     * 首页
     *
     * @return array
     */
    protected function index(): array
    {
        return [
            'name' => 'Kode/Fibers Web UI',
            'version' => '2.6.0',
            'endpoints' => [
                'GET /api/status' => '获取纤程池状态',
                'GET /api/profiler/records' => '获取性能分析记录',
                'GET /api/profiler/stats' => '获取性能统计',
                'GET /api/health' => '健康检查',
                'GET /api/metrics' => '获取指标数据',
            ],
        ];
    }

    /**
     * 获取纤程池状态
     *
     * @return array
     */
    protected function getPoolStatus(): array
    {
        if (!$this->pool) {
            return ['error' => 'Pool not configured', 'code' => 503];
        }
        
        return $this->pool->getStats();
    }

    /**
     * 获取性能分析记录
     *
     * @return array
     */
    protected function getProfilerRecords(): array
    {
        if (!$this->profiler) {
            return ['error' => 'Profiler not configured', 'code' => 503];
        }
        
        return [
            'records' => $this->profiler->records(),
            'count' => count($this->profiler->records()),
        ];
    }

    /**
     * 获取性能统计
     *
     * @return array
     */
    protected function getProfilerStats(): array
    {
        if (!$this->profiler) {
            return ['error' => 'Profiler not configured', 'code' => 503];
        }
        
        $records = $this->profiler->records();
        
        if (empty($records)) {
            return [
                'total_tasks' => 0,
                'success_rate' => 0,
                'avg_duration_ms' => 0,
                'total_memory_delta' => 0,
            ];
        }
        
        $successCount = count(array_filter($records, fn($r) => $r['status'] === 'success'));
        $totalDuration = array_sum(array_column($records, 'duration_ms'));
        $totalMemory = array_sum(array_column($records, 'memory_delta'));
        
        return [
            'total_tasks' => count($records),
            'success_count' => $successCount,
            'failed_count' => count($records) - $successCount,
            'success_rate' => round($successCount / count($records) * 100, 2),
            'avg_duration_ms' => round($totalDuration / count($records), 3),
            'total_duration_ms' => round($totalDuration, 3),
            'total_memory_delta' => $totalMemory,
            'avg_memory_delta' => round($totalMemory / count($records)),
        ];
    }

    /**
     * 健康检查
     *
     * @return array
     */
    protected function healthCheck(): array
    {
        return [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];
    }

    /**
     * 获取指标数据
     *
     * @return array
     */
    protected function getMetrics(): array
    {
        $metrics = [
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'uptime' => time() - ($_SERVER['REQUEST_TIME'] ?? time()),
        ];
        
        if ($this->pool) {
            $metrics['pool'] = $this->pool->getStats();
        }
        
        if ($this->profiler) {
            $metrics['profiler'] = $this->getProfilerStats();
        }
        
        return $metrics;
    }

    /**
     * 获取服务地址
     *
     * @return string
     */
    public function getAddress(): string
    {
        return "http://{$this->host}:{$this->port}";
    }

    /**
     * 创建 Web UI 实例
     *
     * @param array $options 配置选项
     * @return self
     */
    public static function make(array $options = []): self
    {
        return new self($options);
    }
}
