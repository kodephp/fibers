<?php

declare(strict_types=1);

/**
 * 原生协程引擎（Swoole / Swow）单场景压测子进程
 *
 * Swoole 与 Swow 都会接管 Zend VM 的栈切换钩子，同一进程内互斥，无法同时加载。
 * 因此由 benchmarks/bench.php 以「独立子进程 + 只加载单一扩展」的方式分别测量，
 * 再把结果汇总进同一张对比表。
 *
 * 用法（由 bench.php 调用，也可手工执行）：
 *   php -d extension=<ext.so> benchmarks/bench_native_worker.php <engine> <scenario> <ops>
 *
 * 输出协议：
 *   OK   <median_seconds> <memory_bytes>
 *   SKIP <reason>
 *   FAIL <message>
 *
 * 每个场景都带完成度校验（计数不符直接 FAIL），避免出现「协程没跑完就停表」
 * 造成的虚高数字 —— 这是原生协程压测里最常见的陷阱：
 *   - Swoole\Coroutine\run() 会阻塞到所有协程结束，可以安全包裹；
 *   - Swow\Coroutine::run() 只是启动协程，首次让出就返回，不能当作 barrier。
 *     Swow 场景一律在主协程内直接等待。
 */

$engine = $argv[1] ?? '';
$scenario = $argv[2] ?? '';
$ops = (int) ($argv[3] ?? 0);

if (!in_array($engine, ['swoole', 'swow'], true) || $ops <= 0) {
    echo "FAIL invalid args\n";
    exit(1);
}

if (!extension_loaded($engine)) {
    echo "SKIP 扩展未加载\n";
    exit(0);
}

/**
 * 与 Harness 一致的「预热 2 轮 + 实测 5 轮取中位数」
 *
 * @return array{0: float, 1: int}
 */
function measureNative(callable $body): array
{
    for ($i = 0; $i < 2; $i++) {
        $body();
    }

    $durations = [];
    $memories = [];

    for ($i = 0; $i < 5; $i++) {
        gc_collect_cycles();
        $memBefore = memory_get_usage();
        $start = hrtime(true);

        $body();

        $durations[] = (hrtime(true) - $start) / 1e9;
        $memories[] = memory_get_usage() - $memBefore;
    }

    sort($durations);
    sort($memories);
    $middle = intdiv(count($durations), 2);

    return [(float) $durations[$middle], (int) $memories[$middle]];
}

try {
    $body = match ($scenario) {
        'spawn' => nativeSpawn($engine, $ops),
        'switch' => nativeSwitch($engine, $ops),
        'timer' => nativeTimer($engine, $ops),
        'channel' => nativeChannel($engine, $ops),
        'aggregate' => nativeAggregate($engine, $ops),
        default => throw new RuntimeException("未知场景 {$scenario}"),
    };

    [$median, $mem] = measureNative($body);

    echo "OK {$median} {$mem}\n";
} catch (Throwable $e) {
    echo 'FAIL ' . str_replace("\n", ' ', $e->getMessage()) . "\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// 场景 1：协程创建与完成
// ---------------------------------------------------------------------------

function nativeSpawn(string $engine, int $n): callable
{
    if ($engine === 'swoole') {
        return static function () use ($n): void {
            $done = 0;

            \Swoole\Coroutine\run(static function () use ($n, &$done): void {
                $wg = new \Swoole\Coroutine\WaitGroup();

                for ($i = 0; $i < $n; $i++) {
                    $wg->add();
                    \Swoole\Coroutine::create(static function () use ($wg, &$done): void {
                        $done++;
                        $wg->done();
                    });
                }

                $wg->wait();
            });

            if ($done !== $n) {
                throw new RuntimeException("spawn 完成数不符：{$done}/{$n}");
            }
        };
    }

    return static function () use ($n): void {
        $done = 0;
        $wg = new \Swow\Sync\WaitGroup();

        for ($i = 0; $i < $n; $i++) {
            $wg->add(1);
            \Swow\Coroutine::run(static function () use ($wg, &$done): void {
                $done++;
                $wg->done();
            });
        }

        $wg->wait();

        if ($done !== $n) {
            throw new RuntimeException("spawn 完成数不符：{$done}/{$n}");
        }
    };
}

// ---------------------------------------------------------------------------
// 场景 2：协程切换
// 两个兄弟协程互相 yield / resume，N 次切换 = N/2 次让出 + N/2 次唤醒
// ---------------------------------------------------------------------------

function nativeSwitch(string $engine, int $n): callable
{
    $half = intdiv($n, 2);

    if ($engine === 'swoole') {
        return static function () use ($n, $half): void {
            $count = 0;

            \Swoole\Coroutine\run(static function () use ($half, &$count): void {
                $cidA = \Swoole\Coroutine::create(static function () use ($half, &$count): void {
                    for ($i = 0; $i < $half; $i++) {
                        \Swoole\Coroutine::yield();
                        $count++;
                    }
                });

                \Swoole\Coroutine::create(static function () use ($half, $cidA, &$count): void {
                    for ($i = 0; $i < $half; $i++) {
                        \Swoole\Coroutine::resume($cidA);
                        $count++;
                    }
                });
            });

            // 末次 yield 后无人唤醒，允许 1 次误差
            if ($count < $n - 2) {
                throw new RuntimeException("switch 切换数不符：{$count}/{$n}");
            }
        };
    }

    return static function () use ($n, $half): void {
        $count = 0;
        $done = new \Swow\Channel(1);

        $a = \Swow\Coroutine::run(static function () use ($half, &$count): void {
            for ($i = 0; $i < $half; $i++) {
                \Swow\Coroutine::yield();
                $count++;
            }
        });

        \Swow\Coroutine::run(static function () use ($half, $a, $done, &$count): void {
            for ($i = 0; $i < $half; $i++) {
                $a->resume();
                $count++;
            }

            $done->push(1);
        });

        $done->pop();

        if ($count < $n - 2) {
            throw new RuntimeException("switch 切换数不符：{$count}/{$n}");
        }
    };
}

// ---------------------------------------------------------------------------
// 场景 3：定时器调度
// Swoole 定时器最小粒度 1ms（0 会告警并被拒绝）；Swow 无独立定时器注册 API，
// 用 msleep(0) 走 libcat 定时器路径，两者语义均为「最短延迟后回调」
// ---------------------------------------------------------------------------

function nativeTimer(string $engine, int $n): callable
{
    if ($engine === 'swoole') {
        return static function () use ($n): void {
            $fired = 0;

            \Swoole\Coroutine\run(static function () use ($n, &$fired): void {
                $wg = new \Swoole\Coroutine\WaitGroup();

                for ($i = 0; $i < $n; $i++) {
                    $wg->add();
                    \Swoole\Timer::after(1, static function () use ($wg, &$fired): void {
                        $fired++;
                        $wg->done();
                    });
                }

                $wg->wait();
            });

            if ($fired !== $n) {
                throw new RuntimeException("timer 触发数不符：{$fired}/{$n}");
            }
        };
    }

    return static function () use ($n): void {
        $fired = 0;
        $wg = new \Swow\Sync\WaitGroup();

        for ($i = 0; $i < $n; $i++) {
            $wg->add(1);
            \Swow\Coroutine::run(static function () use ($wg, &$fired): void {
                msleep(0);
                $fired++;
                $wg->done();
            });
        }

        $wg->wait();

        if ($fired !== $n) {
            throw new RuntimeException("timer 触发数不符：{$fired}/{$n}");
        }
    };
}

// ---------------------------------------------------------------------------
// 场景 4：Channel 吞吐（单生产者 / 单消费者，缓冲 1024）
// ---------------------------------------------------------------------------

function nativeChannel(string $engine, int $n): callable
{
    if ($engine === 'swoole') {
        return static function () use ($n): void {
            $received = 0;

            \Swoole\Coroutine\run(static function () use ($n, &$received): void {
                $ch = new \Swoole\Coroutine\Channel(1024);

                \Swoole\Coroutine::create(static function () use ($ch, $n): void {
                    for ($i = 0; $i < $n; $i++) {
                        $ch->push($i);
                    }
                });

                for ($i = 0; $i < $n; $i++) {
                    $ch->pop();
                    $received++;
                }
            });

            if ($received !== $n) {
                throw new RuntimeException("channel 收到数不符：{$received}/{$n}");
            }
        };
    }

    return static function () use ($n): void {
        $received = 0;
        $ch = new \Swow\Channel(1024);

        \Swow\Coroutine::run(static function () use ($ch, $n): void {
            for ($i = 0; $i < $n; $i++) {
                $ch->push($i);
            }
        });

        for ($i = 0; $i < $n; $i++) {
            $ch->pop();
            $received++;
        }

        if ($received !== $n) {
            throw new RuntimeException("channel 收到数不符：{$received}/{$n}");
        }
    };
}

// ---------------------------------------------------------------------------
// 场景 5：并发聚合
// ---------------------------------------------------------------------------

function nativeAggregate(string $engine, int $n): callable
{
    if ($engine === 'swoole') {
        return static function () use ($n): void {
            $count = 0;

            \Swoole\Coroutine\run(static function () use ($n, &$count): void {
                $wg = new \Swoole\Coroutine\WaitGroup();
                $results = [];

                for ($i = 0; $i < $n; $i++) {
                    $id = $i;
                    $wg->add();
                    \Swoole\Coroutine::create(static function () use ($wg, $id, &$results): void {
                        $results[$id] = $id * 2;
                        $wg->done();
                    });
                }

                $wg->wait();
                $count = count($results);
            });

            if ($count !== $n) {
                throw new RuntimeException("aggregate 结果数不符：{$count}/{$n}");
            }
        };
    }

    return static function () use ($n): void {
        $results = [];
        $wg = new \Swow\Sync\WaitGroup();

        for ($i = 0; $i < $n; $i++) {
            $id = $i;
            $wg->add(1);
            \Swow\Coroutine::run(static function () use ($id, &$results, $wg): void {
                $results[$id] = $id * 2;
                $wg->done();
            });
        }

        $wg->wait();

        if (count($results) !== $n) {
            throw new RuntimeException('aggregate 结果数不符：' . count($results) . "/{$n}");
        }
    };
}
