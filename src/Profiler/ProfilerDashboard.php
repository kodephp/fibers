<?php

declare(strict_types=1);

namespace Kode\Fibers\Profiler;

class ProfilerDashboard
{
    public static function renderHtml(array $records): string
    {
        $rows = '';
        foreach ($records as $record) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%sms</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars((string) ($record['name'] ?? '')),
                htmlspecialchars((string) ($record['status'] ?? '')),
                htmlspecialchars((string) ($record['duration_ms'] ?? 0)),
                htmlspecialchars((string) ($record['memory_delta'] ?? 0)),
                htmlspecialchars((string) ($record['error'] ?? ''))
            );
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>Fiber Profiler</title></head><body>'
            . '<h1>Fiber Profiler Dashboard</h1>'
            . '<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>名称</th><th>状态</th><th>耗时</th><th>内存变化</th><th>错误</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table></body></html>';
    }
}
