<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kode\Fibers\Concurrency\Scheduler;
use Kode\Fibers\Concurrency\Timer;
use Kode\Fibers\Concurrency\TimerQueue;

$n = 20_000;

function bench(string $label, callable $fn, int $n): void
{
    $best = PHP_FLOAT_MAX;

    for ($r = 0; $r < 5; $r++) {
        $t = hrtime(true);
        $fn();
        $d = (hrtime(true) - $t) / 1e9;
        $best = min($best, $d);
    }

    printf("%-46s %10.3f ms  %12s ops/s  %7.4f us/op\n", $label, $best * 1000, number_format($n / $best), $best / $n * 1e6);
}

$cb = static function (): void {
};

// T0：只造 Timer 对象
bench('T0 仅 new Timer', static function () use ($n, $cb): void {
    for ($i = 0; $i < $n; $i++) {
        new Timer(1.0, $i, $cb, null, null, null);
    }
}, $n);

// T0b：造对象但 id 用常量（衡量拼串开销）
bench('T0b 同上（对照）', static function () use ($n, $cb): void {
    for ($i = 0; $i < $n; $i++) {
        new Timer(1.0, $i, $cb, null, null, null);
    }
}, $n);

// T0c：+ 每个都调 now()
bench('T0c 仅 Scheduler::now()', static function () use ($n): void {
    for ($i = 0; $i < $n; $i++) {
        Scheduler::now();
    }
}, $n);

// T1：+ 插入 TimerQueue（at 各不相同，模拟真实 delay(0)）
bench('T1 插入队列（at 单调递增，各不相同）', static function () use ($n, $cb): void {
    $q = new TimerQueue();

    for ($i = 0; $i < $n; $i++) {
        $q->insert(new Timer(Scheduler::now(), $i, $cb, null, null, $q));
    }
}, $n);

// T1b：+ 插入 TimerQueue（at 全部相同，命中同一个桶）
bench('T1b 插入队列（at 全相同，单桶）', static function () use ($n, $cb): void {
    $q = new TimerQueue();
    $at = Scheduler::now();

    for ($i = 0; $i < $n; $i++) {
        $q->insert(new Timer($at, $i, $cb, null, null, $q));
    }
}, $n);

// T2：插入 + 全部取出（at 各不相同）
bench('T2 插入 + 全部到期取出（at 各不相同）', static function () use ($n, $cb): void {
    $q = new TimerQueue();

    for ($i = 0; $i < $n; $i++) {
        $q->insert(new Timer(Scheduler::now(), $i, $cb, null, null, $q));
    }

    $now = Scheduler::now() + 1;

    while ($q->extractExpired($now, PHP_INT_MAX) !== null) {
    }
}, $n);

// T2b：插入 + 全部取出（at 全相同）
bench('T2b 插入 + 全部到期取出（at 全相同）', static function () use ($n, $cb): void {
    $q = new TimerQueue();
    $at = Scheduler::now();

    for ($i = 0; $i < $n; $i++) {
        $q->insert(new Timer($at, $i, $cb, null, null, $q));
    }

    $now = $at + 1;

    while ($q->extractExpired($now, PHP_INT_MAX) !== null) {
    }
}, $n);

// T3：完整 Scheduler
bench('T3 Scheduler delay(0) + run()', static function () use ($n): void {
    $scheduler = new Scheduler();
    $fired = 0;

    for ($i = 0; $i < $n; $i++) {
        $scheduler->delay(0.0, static function () use (&$fired): void {
            $fired++;
        });
    }

    $scheduler->run();
}, $n);
