<?php

declare(strict_types=1);

namespace Kode\Fibers\Benchmarks;

/**
 * 压测执行器
 *
 * 采用「预热 + 多轮取中位数」策略，避免 JIT 预热、GC 抖动与偶发系统噪声
 * 干扰结果。所有计时基于 hrtime()，不受系统时钟调整影响。
 */
final class Harness
{
    /** @var array<string, array<string, Result>> 场景 => 实现 => 结果 */
    private array $results = [];

    public function __construct(
        private readonly int $rounds = 5,
        private readonly int $warmup = 2,
    ) {
    }

    /**
     * 运行一个被测实现
     *
     * @param string   $scenario 场景名
     * @param string   $impl     实现名（kode/fibers、amphp 等）
     * @param int      $ops      单轮完成的操作数，用于换算 ops/s
     * @param callable $body     被测代码，每轮完整执行一次
     */
    public function measure(string $scenario, string $impl, int $ops, callable $body): void
    {
        for ($i = 0; $i < $this->warmup; $i++) {
            $body();
        }

        $durations = [];
        $memories = [];

        for ($i = 0; $i < $this->rounds; $i++) {
            gc_collect_cycles();
            $memBefore = memory_get_usage();
            $start = hrtime(true);

            $body();

            $elapsed = (hrtime(true) - $start) / 1e9;
            $memories[] = memory_get_usage() - $memBefore;
            $durations[] = $elapsed;
        }

        $this->results[$scenario][$impl] = new Result(
            $impl,
            $ops,
            self::median($durations),
            min($durations),
            (int) self::median($memories),
        );
    }

    /**
     * 直接登记一份「外部测得」的结果
     *
     * 用于 Swoole / Swow 这类必须在独立子进程中测量的实现（两者在 Zend 层互斥，
     * 无法与彼此共存于同一进程），由子进程完成计时后回填到同一张对比表。
     */
    public function record(string $scenario, string $impl, int $ops, float $median, int $memory = 0): void
    {
        $this->results[$scenario][$impl] = new Result($impl, $ops, $median, $median, $memory);
    }

    /**
     * 记录一个不适用/跳过的实现，保持表格列对齐
     */
    public function skip(string $scenario, string $impl, string $reason): void
    {
        $this->results[$scenario][$impl] = Result::skipped($impl, $reason);
    }

    /**
     * @return array<string, array<string, Result>>
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * 输出终端对比表格，以基准实现为 1.00x
     */
    public function report(string $baseline = 'kode/fibers'): void
    {
        foreach ($this->results as $scenario => $impls) {
            echo "\n\033[1m{$scenario}\033[0m\n";
            printf("  %-22s %14s %12s %10s %12s\n", '实现', 'ops/s', '中位耗时', '相对', '内存');
            echo '  ' . str_repeat('-', 74) . "\n";

            $base = $impls[$baseline] ?? null;
            $baseOps = $base !== null && !$base->skipped ? $base->opsPerSecond() : null;

            foreach ($impls as $result) {
                if ($result->skipped) {
                    printf("  %-22s %14s %12s %10s %12s\n", $result->impl, '—', '—', '—', $result->reason);

                    continue;
                }

                $relative = $baseOps !== null && $baseOps > 0
                    ? sprintf('%.2fx', $result->opsPerSecond() / $baseOps)
                    : '—';

                printf(
                    "  %-22s %14s %12s %10s %12s\n",
                    $result->impl,
                    number_format($result->opsPerSecond()),
                    sprintf('%.3f ms', $result->median * 1000),
                    $relative,
                    self::formatBytes($result->memory),
                );
            }
        }

        echo "\n";
    }

    /**
     * 输出 Markdown 表格，便于直接写入文档
     */
    public function toMarkdown(string $baseline = 'kode/fibers'): string
    {
        $out = '';

        foreach ($this->results as $scenario => $impls) {
            $out .= "### {$scenario}\n\n";
            $out .= "| 实现 | ops/s | 中位耗时 | 相对基准 | 内存增量 |\n";
            $out .= "| --- | ---: | ---: | ---: | ---: |\n";

            $base = $impls[$baseline] ?? null;
            $baseOps = $base !== null && !$base->skipped ? $base->opsPerSecond() : null;

            foreach ($impls as $result) {
                if ($result->skipped) {
                    $out .= "| {$result->impl} | — | — | — | {$result->reason} |\n";

                    continue;
                }

                $relative = $baseOps !== null && $baseOps > 0
                    ? sprintf('%.2fx', $result->opsPerSecond() / $baseOps)
                    : '—';

                $out .= sprintf(
                    "| %s | %s | %.3f ms | %s | %s |\n",
                    $result->impl,
                    number_format($result->opsPerSecond()),
                    $result->median * 1000,
                    $relative,
                    self::formatBytes($result->memory),
                );
            }

            $out .= "\n";
        }

        return $out;
    }

    /**
     * @param float[]|int[] $values
     */
    private static function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : (float) $values[$middle];
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return sprintf('%.1f KB', $bytes / 1024);
        }

        return sprintf('%.1f MB', $bytes / 1024 / 1024);
    }
}
