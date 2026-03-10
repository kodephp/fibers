<?php

declare(strict_types=1);

namespace Kode\Fibers\Profiler;

class FiberProfiler
{
    protected array $records = [];

    public function profile(string $name, callable $task): mixed
    {
        $startedAt = microtime(true);
        $memoryBefore = memory_get_usage(true);

        try {
            $result = $task();
            $status = 'success';
            $error = null;
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
            throw $e;
        } finally {
            $endedAt = microtime(true);
            $this->records[] = [
                'name' => $name,
                'status' => $status,
                'duration_ms' => round(($endedAt - $startedAt) * 1000, 3),
                'memory_delta' => memory_get_usage(true) - $memoryBefore,
                'error' => $error,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
            ];
        }

        return $result;
    }

    public function records(): array
    {
        return $this->records;
    }
}
