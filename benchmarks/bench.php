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
}

// ---------------------------------------------------------------------------
// 场景 2：协程切换
// 单个协程反复让出执行权，纯粹衡量上下文切换与挂起/唤醒开销
// ---------------------------------------------------------------------------
if ($wants('switch')) {
    $n = 50_000;
    $scenario = "协程切换（单协程让出 {$n} 次）";

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
}

// ---------------------------------------------------------------------------
// 场景 3：定时器调度
// 大批量定时器的插入与到期派发，检验定时器数据结构（最小堆 vs 其它）
// ---------------------------------------------------------------------------
if ($wants('timer')) {
    $n = 20_000;
    $scenario = "定时器调度（{$n} 个 0 延迟定时器）";

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
}

$harness->report();

if ($emitMarkdown) {
    echo "----- Markdown -----\n\n";
    echo $harness->toMarkdown();
}
