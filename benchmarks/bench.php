<?php

declare(strict_types=1);

/**
 * kode/fibers 与同类协程 / 事件循环库的横向压测
 *
 * 用法：
 *   php benchmarks/bench.php              跑全部场景
 *   php benchmarks/bench.php spawn switch 只跑指定场景
 *   php benchmarks/bench.php --markdown   额外输出 Markdown 表格
 *
 * 公平性说明：
 * 各库提供的抽象层级不同，表格中会明确标注每个实现实际执行的原语，
 * 不把「回调调度」与「协程调度」混为一谈。
 */

use Amp\Future;
use Amp\Pipeline\Queue;
use Kode\Fibers\Benchmarks\Harness;
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Concurrency\Scheduler;
use React\EventLoop\Loop;
use Revolt\EventLoop;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/Result.php';
require __DIR__ . '/Harness.php';

$argvFiltered = array_values(array_filter(
    array_slice($argv, 1),
    static fn(string $a): bool => !str_starts_with($a, '--')
));
$emitMarkdown = in_array('--markdown', $argv, true);
$only = $argvFiltered;

$wants = static fn(string $name): bool => $only === [] || in_array($name, $only, true);

$harness = new Harness(rounds: 5, warmup: 2);

echo "kode/fibers 横向压测\n";
echo 'PHP ' . PHP_VERSION . ' | ' . PHP_OS_FAMILY . " | 5 轮取中位数（预热 2 轮）\n";

// ---------------------------------------------------------------------------
// 原生协程引擎（Swoole / Swow）
//
// 这两个扩展都会接管 Zend VM 的栈切换，同一进程内互斥且无法与本进程共存，
// 因此统一以「独立子进程 + 只加载单一扩展」的方式测量，再汇总到同一张表。
// 可通过环境变量 BENCH_SWOOLE_EXT / BENCH_SWOW_EXT 指定 .so 路径。
// ---------------------------------------------------------------------------

/**
 * 在常见安装目录中定位扩展的 .so 文件
 */
$locateExtension = static function (string $name): ?string {
    $env = getenv('BENCH_' . strtoupper($name) . '_EXT');

    if (is_string($env) && $env !== '' && is_file($env)) {
        return $env;
    }

    $candidates = [];
    $extensionDir = (string) ini_get('extension_dir');

    if ($extensionDir !== '') {
        $candidates[] = rtrim($extensionDir, '/') . '/' . $name . '.so';
    }

    foreach ([
        '/opt/homebrew/Cellar/php*/*/pecl/*/',
        '/usr/local/Cellar/php*/*/pecl/*/',
        '/usr/lib/php/*/',
        '/usr/lib64/php/modules/',
    ] as $pattern) {
        foreach (glob($pattern . $name . '.so') ?: [] as $path) {
            $candidates[] = $path;
        }
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
};

$nativeEngines = [
    'swoole' => ['label' => 'swoole (原生协程)', 'ext' => $locateExtension('swoole')],
    'swow' => ['label' => 'swow (原生协程)', 'ext' => $locateExtension('swow')],
];

/**
 * 以子进程方式跑一个原生引擎的单场景，并把结果写回 harness
 */
$measureNative = static function (
    string $scenario,
    string $scenarioLabel,
    int $ops,
) use ($harness, $nativeEngines): void {
    foreach ($nativeEngines as $engine => $meta) {
        if ($meta['ext'] === null) {
            $harness->skip($scenarioLabel, $meta['label'], '未安装扩展');

            continue;
        }

        $command = sprintf(
            '%s -d extension=%s %s %s %s %d 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($meta['ext']),
            escapeshellarg(__DIR__ . '/bench_native_worker.php'),
            escapeshellarg($engine),
            escapeshellarg($scenario),
            $ops,
        );

        $output = (string) shell_exec($command);
        $line = '';

        foreach (array_reverse(explode("\n", trim($output))) as $candidate) {
            $candidate = trim($candidate);

            if (str_starts_with($candidate, 'OK ')
                || str_starts_with($candidate, 'SKIP ')
                || str_starts_with($candidate, 'FAIL ')) {
                $line = $candidate;

                break;
            }
        }

        if (str_starts_with($line, 'OK ')) {
            [, $median, $memory] = array_pad(explode(' ', $line), 3, '0');
            $harness->record($scenarioLabel, $meta['label'], $ops, (float) $median, (int) $memory);

            continue;
        }

        if (str_starts_with($line, 'SKIP ')) {
            $harness->skip($scenarioLabel, $meta['label'], substr($line, 5));

            continue;
        }

        $harness->skip($scenarioLabel, $meta['label'], $line === '' ? '子进程无输出' : substr($line, 5));
    }
};

$detected = [];

foreach ($nativeEngines as $engine => $meta) {
    $detected[] = $engine . ': ' . ($meta['ext'] ?? '未检测到');
}

echo '原生引擎 | ' . implode(' | ', $detected) . "\n";

// ---------------------------------------------------------------------------
// 场景 1：协程创建与完成
// 从「创建」到「全部跑完」的端到端吞吐，反映调度器的任务分发成本
// ---------------------------------------------------------------------------
if ($wants('spawn')) {
    $n = 20_000;
    $scenario = "协程创建与完成（{$n} 个空任务）";

    $harness->measure($scenario, 'kode/fibers', $n, static function () use ($n): void {
        $scheduler = new Scheduler();

        for ($i = 0; $i < $n; $i++) {
            $scheduler->go(static fn(): int => 1);
        }

        $scheduler->run();
    });

    $harness->measure($scenario, 'amphp/amp v3', $n, static function () use ($n): void {
        $futures = [];

        for ($i = 0; $i < $n; $i++) {
            $futures[] = Amp\async(static fn(): int => 1);
        }

        Future\await($futures);
    });

    $harness->measure($scenario, 'revolt (回调)', $n, static function () use ($n): void {
        $pending = $n;
        $suspension = EventLoop::getSuspension();

        for ($i = 0; $i < $n; $i++) {
            EventLoop::queue(static function () use (&$pending, $suspension): void {
                if (--$pending === 0) {
                    $suspension->resume();
                }
            });
        }

        $suspension->suspend();
    });

    $harness->measure($scenario, 'reactphp (回调)', $n, static function () use ($n): void {
        $pending = $n;

        for ($i = 0; $i < $n; $i++) {
            Loop::futureTick(static function () use (&$pending): void {
                --$pending;
            });
        }

        Loop::run();
    });

    $measureNative('spawn', $scenario, $n);
}

// ---------------------------------------------------------------------------
// 场景 2：协程切换
// 单个协程反复让出执行权，纯粹衡量上下文切换与挂起/唤醒开销
// ---------------------------------------------------------------------------
if ($wants('switch')) {
    $n = 50_000;
    $scenario = "协程切换（{$n} 次让出/唤醒）";

    $harness->measure($scenario, 'kode/fibers', $n, static function () use ($n): void {
        $scheduler = new Scheduler();

        $scheduler->go(static function () use ($scheduler, $n): void {
            for ($i = 0; $i < $n; $i++) {
                $scheduler->yieldNow();
            }
        });

        $scheduler->run();
    });

    $harness->measure($scenario, 'amphp/amp v3', $n, static function () use ($n): void {
        Amp\async(static function () use ($n): void {
            $suspension = EventLoop::getSuspension();

            for ($i = 0; $i < $n; $i++) {
                EventLoop::queue(static fn() => $suspension->resume());
                $suspension->suspend();
            }
        })->await();
    });

    $harness->measure($scenario, 'revolt (Suspension)', $n, static function () use ($n): void {
        $fiber = new Fiber(static function () use ($n): void {
            $suspension = EventLoop::getSuspension();

            for ($i = 0; $i < $n; $i++) {
                EventLoop::queue(static fn() => $suspension->resume());
                $suspension->suspend();
            }
        });

        EventLoop::queue(static fn() => $fiber->start());
        EventLoop::run();
    });

    $harness->skip($scenario, 'reactphp (回调)', '无协程原语');

    $measureNative('switch', $scenario, $n);
}

// ---------------------------------------------------------------------------
// 场景 3：定时器调度
// 大批量定时器的插入与到期派发，检验定时器数据结构（最小堆 vs 其它）
// ---------------------------------------------------------------------------
if ($wants('timer')) {
    $n = 20_000;
    // Swoole 定时器最小粒度为 1ms，Swow 无独立定时器 API（走 msleep(0)），
    // 因此统一表述为「最短延迟」而非严格 0 延迟
    $scenario = "定时器调度（{$n} 个最短延迟定时器）";

    $harness->measure($scenario, 'kode/fibers', $n, static function () use ($n): void {
        $scheduler = new Scheduler();
        $fired = 0;

        for ($i = 0; $i < $n; $i++) {
            $scheduler->delay(0.0, static function () use (&$fired): void {
                $fired++;
            });
        }

        $scheduler->run();
    });

    $harness->measure($scenario, 'revolt', $n, static function () use ($n): void {
        $fired = 0;

        for ($i = 0; $i < $n; $i++) {
            EventLoop::delay(0.0, static function () use (&$fired): void {
                $fired++;
            });
        }

        EventLoop::run();
    });

    $harness->measure($scenario, 'reactphp', $n, static function () use ($n): void {
        $fired = 0;

        for ($i = 0; $i < $n; $i++) {
            Loop::addTimer(0.0, static function () use (&$fired): void {
                $fired++;
            });
        }

        Loop::run();
    });

    $measureNative('timer', $scenario, $n);
}

// ---------------------------------------------------------------------------
// 场景 4：Channel 吞吐
// 单生产者 / 单消费者传递消息，衡量阻塞队列的挂起唤醒效率
// ---------------------------------------------------------------------------
if ($wants('channel')) {
    $n = 50_000;
    $scenario = "Channel 吞吐（{$n} 条消息，缓冲 1024）";

    $harness->measure($scenario, 'kode/fibers', $n, static function () use ($n): void {
        $scheduler = new Scheduler();
        $channel = new Channel('bench', 1024);
        $received = 0;

        $scheduler->go(static function () use ($channel, $n): void {
            for ($i = 0; $i < $n; $i++) {
                $channel->push($i);
            }

            $channel->close();
        });

        $scheduler->go(static function () use ($channel, $n, &$received): void {
            for ($i = 0; $i < $n; $i++) {
                $channel->pop();
                $received++;
            }
        });

        $scheduler->run();
    });

    $harness->measure($scenario, 'amphp/pipeline', $n, static function () use ($n): void {
        Amp\async(static function () use ($n): void {
            $queue = new Queue(1024);
            $pipeline = $queue->pipe();

            $producer = Amp\async(static function () use ($queue, $n): void {
                for ($i = 0; $i < $n; $i++) {
                    $queue->push($i);
                }

                $queue->complete();
            });

            $consumer = Amp\async(static function () use ($pipeline): void {
                foreach ($pipeline as $ignored) {
                    // 消费
                }
            });

            Future\await([$producer, $consumer]);
        })->await();
    });

    $harness->skip($scenario, 'reactphp', '无阻塞 Channel');

    $measureNative('channel', $scenario, $n);
}

// ---------------------------------------------------------------------------
// 场景 5：并发聚合
// 并发跑一批带返回值的任务并收集结果，最贴近真实业务用法
// ---------------------------------------------------------------------------
if ($wants('aggregate')) {
    $n = 10_000;
    $scenario = "并发聚合（{$n} 个任务收集结果）";

    $harness->measure($scenario, 'kode/fibers', $n, static function () use ($n): void {
        $scheduler = new Scheduler();
        $tasks = [];

        for ($i = 0; $i < $n; $i++) {
            $tasks[] = static fn(): int => $i * 2;
        }

        $results = $scheduler->runAll($tasks);

        if (count($results) !== $n) {
            throw new RuntimeException('结果数量不符');
        }
    });

    $harness->measure($scenario, 'amphp/amp v3', $n, static function () use ($n): void {
        $futures = [];

        for ($i = 0; $i < $n; $i++) {
            $futures[] = Amp\async(static fn(): int => $i * 2);
        }

        $results = Future\await($futures);

        if (count($results) !== $n) {
            throw new RuntimeException('结果数量不符');
        }
    });

    $harness->skip($scenario, 'reactphp', '需 Promise 组合，语义不等价');

    $measureNative('aggregate', $scenario, $n);
}

$harness->report();

if ($emitMarkdown) {
    echo "----- Markdown -----\n\n";
    echo $harness->toMarkdown();
}
