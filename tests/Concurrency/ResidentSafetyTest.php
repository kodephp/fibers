<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests\Concurrency;

use Kode\Fibers\Concurrency\CancelledException;
use Kode\Fibers\Concurrency\CancellationTokenSource;
use Kode\Fibers\Concurrency\Mutex;
use Kode\Fibers\Concurrency\Scheduler;
use Kode\Fibers\Concurrency\TimeoutException;
use Kode\Fibers\Exceptions\FiberException;
use Kode\Fibers\Fibers;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * 常驻进程语义回归：取消信号在挂起窗口里的投递、无循环时的超时令牌、
 * 池化纤程下的锁归属、以及无人 join 的协程异常上报。
 */
final class ResidentSafetyTest extends TestCase
{
    /**
     * 取消到达于「上一次挂起已被消费、下一次挂起尚未登记」的窗口时过去会永久丢失
     * （表现为 withTimeout 的任务跑完了还正常返回）。现在必须在下一个挂起点补投。
     */
    public function testCancellationLandsOnNextParkWhenSuspensionWasAlreadyConsumed(): void
    {
        $scheduler = new Scheduler();
        $outcome = null;
        $first = null;

        $coroutine = $scheduler->go(static function () use ($scheduler, &$first, &$outcome): void {
            $first = $scheduler->park();
            $first->suspend();

            // 到这里挂起句柄已被消费：此刻到达的取消没有可投之处
            try {
                $scheduler->park();
                $outcome = 'park-returned';
            } catch (CancelledException) {
                $outcome = 'cancelled';
            }
        });

        // 无事可做 → run() 返回，协程停在上一次挂起点
        $scheduler->run();
        self::assertNotNull($first, '协程应已登记挂起句柄');

        $first->resume();
        $coroutine->cancel('测试用取消');

        $scheduler->run();

        self::assertSame('cancelled', $outcome, '取消必须在下一个挂起点补投，而不是静默丢失');
    }

    /**
     * 任务的短睡眠串与超时定时器落在同一轮 expireTimers()：修复前实测约 1/4 概率丢闹钟。
     */
    public function testWithTimeoutAlwaysInterruptsAnOverrunningTask(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $thrown = false;

            try {
                Fibers::withTimeout(static function (): string {
                    for ($k = 0; $k < 20; $k++) {
                        Fibers::sleep(0.001);
                    }

                    return 'too-late';
                }, 0.004);
            } catch (TimeoutException) {
                $thrown = true;
            }

            self::assertTrue($thrown, "第 {$i} 轮：超时必须抛 TimeoutException，不能放任务跑完");
        }
    }

    /**
     * 循环外创建的超时令牌过去挂到「没人驱动的进程级默认调度器」上：
     * 永不超时，还把闭包永久钉在单例里。现在按单调时钟惰性判定。
     */
    public function testTimeoutTokenExpiresWithoutARunningLoop(): void
    {
        Scheduler::resetDefault();
        $timersBefore = Scheduler::default()->stats()['timers'];

        $token = CancellationTokenSource::withTimeout(0.01)->token();

        self::assertSame(
            $timersBefore,
            Scheduler::default()->stats()['timers'],
            '无事件循环时不应把定时器挂进进程级默认调度器'
        );
        self::assertFalse($token->isCancelled(), '刚创建不应已超时');

        usleep(30000);

        self::assertTrue($token->isCancelled(), '到点必须超时（没有任何循环在驱动它）');
        self::assertInstanceOf(TimeoutException::class, $token->reason());

        $received = null;
        $token->subscribe(static function (Throwable $e) use (&$received): void {
            $received = $e::class;
        });
        self::assertSame(TimeoutException::class, $received, '超时后注册的订阅者应立即收到');
    }

    public function testTimeoutSourceStillUsesTimersInsideARunningLoop(): void
    {
        Fibers::run(static function (): void {
            $token = CancellationTokenSource::withTimeout(0.01)->token();

            Fibers::sleep(0.03);

            self::assertTrue($token->isCancelled(), '循环内仍应由定时器驱动超时');
        });
    }

    /**
     * 纤程池化复用：A 协程退出后同一个 Fiber 会被 B 借走。以 Fiber 认持锁者，
     * B 既会被误判重入，又能解开一把自己从没拿过的锁。
     */
    public function testPooledFiberReuseDoesNotInheritMutexOwnership(): void
    {
        $mutex = new Mutex();
        $observed = [];

        Fibers::loop([
            'A' => static function () use ($mutex): string {
                $mutex->lock();

                // 故意不 unlock：模拟持锁协程异常退出后的弃锁
                return 'a';
            },
            'B' => static function () use ($mutex, &$observed): string {
                $observed['tryLock'] = $mutex->tryLock();

                try {
                    $mutex->unlock();
                    $observed['unlock'] = 'accepted';
                } catch (FiberException) {
                    $observed['unlock'] = 'rejected';
                }

                return 'b';
            },
        ]);

        self::assertFalse($observed['tryLock'], '锁仍被弃持时不得让另一个协程拿到');
        self::assertSame('rejected', $observed['unlock'], '非持锁协程不得解他人的锁');
    }

    public function testSameCoroutineStillDetectsMutexReentry(): void
    {
        $mutex = new Mutex();
        $outcome = null;

        Fibers::loop([
            'holder' => static function () use ($mutex, &$outcome): void {
                $mutex->lock();

                try {
                    $mutex->lock();
                    $outcome = 'reentry-allowed';
                } catch (FiberException) {
                    $outcome = 'reentry-rejected';
                }

                $mutex->unlock();
            },
        ]);

        self::assertSame('reentry-rejected', $outcome, '同一协程重复加锁仍须报错');
    }

    /**
     * 协程抛错却从没人 join：过去只登记不上报，异常在进程里蒸发。
     *
     * 本轮失败要留到下一个循环边界才定性（join 发生在 run() 之后的写法不能误报），
     * 所以这里跑第二轮空循环。
     */
    public function testUnhandledCoroutineErrorsAreReportedWhenTheLoopEnds(): void
    {
        $scheduler = new Scheduler();
        $reported = [];

        $scheduler->setErrorHandler(static function (Throwable $e) use (&$reported): void {
            $reported[] = $e->getMessage();
        });

        $scheduler->go(static function (): void {
            throw new RuntimeException('没人 join 的失败');
        });

        $scheduler->run();
        self::assertSame([], $reported, '本轮刚失败、可能马上就有人 join，不该立即定性为丢失');

        $scheduler->run();
        self::assertContains('没人 join 的失败', $reported, '跨过一个完整循环仍无人读取，必须在收尾上报');
        self::assertSame([], $scheduler->unhandledErrors(), '上报后即出列，不得重复上报');
    }

    /**
     * 常驻 worker 在一次请求收尾就要把异常说干净，等不到下一个循环边界。
     */
    public function testFlushUnhandledErrorsReportsImmediately(): void
    {
        $scheduler = new Scheduler();
        $reported = [];

        $scheduler->setErrorHandler(static function (Throwable $e) use (&$reported): void {
            $reported[] = $e->getMessage();
        });

        $scheduler->go(static function (): void {
            throw new RuntimeException('请求收尾前的失败');
        });

        $scheduler->run();
        self::assertSame([], $reported);

        $scheduler->flushUnhandledErrors();
        self::assertSame(['请求收尾前的失败'], $reported);
        self::assertSame([], $scheduler->unhandledErrors());
    }

    /**
     * 内置帮助方法的 join() 发生在 run() 之后：这一类正常用法不能产生误报，
     * 否则常驻进程的错误流会被"未被读取的异常"噪声刷满。
     */
    public function testTimeoutHelperDoesNotProduceFalseUnreadErrors(): void
    {
        $scheduler = new Scheduler();
        $reported = [];

        $scheduler->setErrorHandler(static function (Throwable $e) use (&$reported): void {
            $reported[] = $e::class . ':' . $e->getMessage();
        });

        $coroutine = $scheduler->go(static function () use ($scheduler): string {
            try {
                $scheduler->withTimeout(static function () use ($scheduler): string {
                    $scheduler->sleep(0.05);

                    return 'never';
                }, 0.005);
            } catch (TimeoutException) {
                return 'timed-out';
            }

            return 'no-timeout';
        });

        self::assertSame('timed-out', $coroutine->join());
        self::assertSame([], $scheduler->unhandledErrors(), '结果被读走的超时不应留在待报清单里');

        $scheduler->run();
        $scheduler->flushUnhandledErrors();
        self::assertSame([], $reported, '超时被正常处理时不该上报任何"未被读取的异常"');
    }

    public function testJoinedCoroutineErrorsAreNotReportedAgain(): void
    {
        $scheduler = new Scheduler();
        $reported = [];
        $caught = null;

        $scheduler->setErrorHandler(static function (Throwable $e) use (&$reported): void {
            $reported[] = $e->getMessage();
        });

        $scheduler->go(static function () use ($scheduler, &$caught): void {
            $child = $scheduler->go(static function (): void {
                throw new RuntimeException('已被读取的失败');
            });

            try {
                $child->join();
            } catch (Throwable $e) {
                $caught = $e->getMessage();
            }
        });

        $scheduler->run();

        self::assertSame('已被读取的失败', $caught, 'join 应把异常交给调用方');
        self::assertSame([], $reported, '结果已被读取的协程不应再上报一次');
    }
}
