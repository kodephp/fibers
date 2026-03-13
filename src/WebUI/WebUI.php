<?php

declare(strict_types=1);

namespace Kode\Fibers\WebUI;

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Profiler\FiberProfiler;

/**
 * Web 管理界面
 *
 * 提供 Web UI 管理纤程池和任务的可视化界面，整合性能监控面板功能。
 */
class WebUI
{
    protected ?FiberPool $pool = null;
    protected ?FiberProfiler $profiler = null;
    protected int $port = 8080;
    protected string $host = '0.0.0.0';
    protected bool $running = false;
    protected string $title = 'Kode/Fibers Dashboard';
    protected string $theme = 'light';

    public function __construct(array $options = [])
    {
        $this->port = (int) ($options['port'] ?? 8080);
        $this->host = (string) ($options['host'] ?? '0.0.0.0');
        $this->pool = $options['pool'] ?? null;
        $this->profiler = $options['profiler'] ?? null;
        $this->title = (string) ($options['title'] ?? 'Kode/Fibers Dashboard');
        $this->theme = (string) ($options['theme'] ?? 'light');
    }

    public function setPool(FiberPool $pool): self
    {
        $this->pool = $pool;
        return $this;
    }

    public function setProfiler(FiberProfiler $profiler): self
    {
        $this->profiler = $profiler;
        return $this;
    }

    public function start(): void
    {
        $this->running = true;
        $this->handleRequest();
    }

    public function stop(): void
    {
        $this->running = false;
    }

    protected function handleRequest(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? 'application/json';
        
        $isHtmlRequest = str_contains($accept, 'text/html');
        
        if ($isHtmlRequest && $path === '/') {
            header('Content-Type: text/html; charset=utf-8');
            echo $this->renderDashboard();
            return;
        }
        
        header('Content-Type: application/json; charset=utf-8');
        $response = $this->route($path, $method);
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function route(string $path, string $method): array
    {
        return match ($path) {
            '/', '/api' => $this->index(),
            '/api/status', '/api/pool/status' => $this->getPoolStatus(),
            '/api/profiler/records' => $this->getProfilerRecords(),
            '/api/profiler/stats' => $this->getProfilerStats(),
            '/api/health' => $this->healthCheck(),
            '/api/metrics' => $this->getMetrics(),
            '/api/dashboard' => $this->getDashboardData(),
            default => ['error' => 'Not Found', 'code' => 404],
        };
    }

    protected function index(): array
    {
        return [
            'name' => 'Kode/Fibers Web UI',
            'version' => '2.7.0',
            'endpoints' => [
                'GET /' => '可视化仪表盘页面',
                'GET /api/status' => '获取纤程池状态',
                'GET /api/profiler/records' => '获取性能分析记录',
                'GET /api/profiler/stats' => '获取性能统计',
                'GET /api/health' => '健康检查',
                'GET /api/metrics' => '获取指标数据',
                'GET /api/dashboard' => '获取仪表盘数据',
            ],
        ];
    }

    protected function getPoolStatus(): array
    {
        if (!$this->pool) {
            return ['error' => 'Pool not configured', 'code' => 503];
        }
        return $this->pool->getStats();
    }

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

    protected function getProfilerStats(): array
    {
        if (!$this->profiler) {
            return ['error' => 'Profiler not configured', 'code' => 503];
        }
        
        $records = $this->profiler->records();
        
        if (empty($records)) {
            return ['total_tasks' => 0, 'success_rate' => 0, 'avg_duration_ms' => 0, 'total_memory_delta' => 0];
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

    protected function getDashboardData(): array
    {
        return [
            'title' => $this->title,
            'theme' => $this->theme,
            'health' => $this->healthCheck(),
            'metrics' => $this->getMetrics(),
            'pool' => $this->pool ? $this->pool->getStats() : null,
            'profiler' => $this->profiler ? $this->getProfilerStats() : null,
            'records' => $this->profiler ? array_slice($this->profiler->records(), -50) : [],
        ];
    }

    public function renderDashboard(): string
    {
        $data = $this->getDashboardData();
        $isDark = $this->theme === 'dark';
        $bgColor = $isDark ? '#1a1a2e' : '#f8fafc';
        $cardBg = $isDark ? '#16213e' : '#ffffff';
        $textColor = $isDark ? '#e2e8f0' : '#1e293b';
        $borderColor = $isDark ? '#334155' : '#e2e8f0';
        
        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($this->title) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: ' . $bgColor . '; color: ' . $textColor . '; min-height: 100vh; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 2rem; margin-bottom: 8px; }
        .header p { opacity: 0.7; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: ' . $cardBg . '; border-radius: 12px; padding: 20px; border: 1px solid ' . $borderColor . '; }
        .card h2 { font-size: 1.1rem; margin-bottom: 15px; opacity: 0.8; }
        .stat { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid ' . $borderColor . '; }
        .stat:last-child { border-bottom: none; }
        .stat-label { opacity: 0.7; }
        .stat-value { font-weight: 600; }
        .success { color: #22c55e; }
        .error { color: #ef4444; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid ' . $borderColor . '; }
        th { opacity: 0.7; font-weight: 500; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .badge-error { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .refresh-btn { position: fixed; bottom: 20px; right: 20px; padding: 12px 24px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; }
        .refresh-btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 ' . htmlspecialchars($this->title) . '</h1>
            <p>PHP ' . PHP_VERSION . ' | 更新时间: ' . date('Y-m-d H:i:s') . '</p>
        </div>
        
        <div class="grid">
            <div class="card">
                <h2>📊 系统状态</h2>
                <div class="stat"><span class="stat-label">PHP 版本</span><span class="stat-value">' . PHP_VERSION . '</span></div>
                <div class="stat"><span class="stat-label">内存使用</span><span class="stat-value">' . $this->formatBytes($data['health']['memory_usage']) . '</span></div>
                <div class="stat"><span class="stat-label">内存峰值</span><span class="stat-value">' . $this->formatBytes($data['health']['memory_peak']) . '</span></div>
                <div class="stat"><span class="stat-label">内存限制</span><span class="stat-value">' . ini_get('memory_limit') . '</span></div>
            </div>
            
            <div class="card">
                <h2>⚡ 纤程池状态</h2>
                ' . ($data['pool'] ? '
                <div class="stat"><span class="stat-label">活跃纤程</span><span class="stat-value">' . ($data['pool']['active_fibers'] ?? 0) . '</span></div>
                <div class="stat"><span class="stat-label">总任务数</span><span class="stat-value">' . ($data['pool']['total_tasks'] ?? 0) . '</span></div>
                <div class="stat"><span class="stat-label">完成任务</span><span class="stat-value success">' . ($data['pool']['completed_tasks'] ?? 0) . '</span></div>
                <div class="stat"><span class="stat-label">失败任务</span><span class="stat-value error">' . ($data['pool']['failed_tasks'] ?? 0) . '</span></div>
                ' : '<p style="opacity:0.5">未配置纤程池</p>') . '
            </div>
            
            <div class="card">
                <h2>📈 性能统计</h2>
                ' . ($data['profiler'] ? '
                <div class="stat"><span class="stat-label">总任务数</span><span class="stat-value">' . ($data['profiler']['total_tasks'] ?? 0) . '</span></div>
                <div class="stat"><span class="stat-label">成功率</span><span class="stat-value success">' . ($data['profiler']['success_rate'] ?? 0) . '%</span></div>
                <div class="stat"><span class="stat-label">平均耗时</span><span class="stat-value">' . ($data['profiler']['avg_duration_ms'] ?? 0) . ' ms</span></div>
                <div class="stat"><span class="stat-label">总耗时</span><span class="stat-value">' . ($data['profiler']['total_duration_ms'] ?? 0) . ' ms</span></div>
                ' : '<p style="opacity:0.5">未配置性能分析器</p>') . '
            </div>
        </div>
        
        <div class="card">
            <h2>📋 最近任务记录</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>名称</th><th>状态</th><th>耗时</th><th>内存变化</th><th>错误</th></tr>
                    </thead>
                    <tbody>
                        ' . $this->renderRecords($data['records']) . '
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <button class="refresh-btn" onclick="location.reload()">🔄 刷新</button>
    <script>setTimeout(() => location.reload(), 30000);</script>
</body>
</html>';
    }

    protected function renderRecords(array $records): string
    {
        if (empty($records)) {
            return '<tr><td colspan="5" style="text-align:center;opacity:0.5">暂无记录</td></tr>';
        }
        
        $html = '';
        foreach (array_reverse($records) as $record) {
            $statusClass = ($record['status'] ?? '') === 'success' ? 'badge-success' : 'badge-error';
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars((string) ($record['name'] ?? '')) . '</td>';
            $html .= '<td><span class="badge ' . $statusClass . '">' . htmlspecialchars((string) ($record['status'] ?? '')) . '</span></td>';
            $html .= '<td>' . htmlspecialchars((string) ($record['duration_ms'] ?? 0)) . ' ms</td>';
            $html .= '<td>' . $this->formatBytes($record['memory_delta'] ?? 0) . '</td>';
            $html .= '<td class="error">' . htmlspecialchars((string) ($record['error'] ?? '')) . '</td>';
            $html .= '</tr>';
        }
        
        return $html;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getAddress(): string
    {
        return "http://{$this->host}:{$this->port}";
    }

    public static function make(array $options = []): self
    {
        return new self($options);
    }
}
