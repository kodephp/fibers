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

    /**
     * 载入已测量好的记录（用于从外部记录渲染仪表盘，避免重复测量）
     *
     * @param array<int, array{name:string,status:string,duration_ms:float,memory_delta:int,error?:?string}> $records
     * @return void
     */
    public function loadRecords(array $records): void
    {
        $this->records = array_values($records);
    }
}
