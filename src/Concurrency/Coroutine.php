<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Closure;
use Fiber;
use Throwable;

/**
 * 协程句柄
 *
 * 由 {@see Scheduler::go()} 创建，代表一个受调度器管理的执行单元。
 * 支持 join（等待结果）、cancel（在下一个挂起点中断）与状态查询。
 *
 * 协程句柄本身不持有 Fiber。真正的执行载体由调度器在启动时从
 * {@see Scheduler} 的 Fiber 复用池中借出：`Fiber::start()` 需要分配一段独立
 * C 栈，实测约 3.6μs，占「创建 + 执行」总成本的九成以上；而复用一个已存在的
 * Fiber（resume + suspend）只需约 0.14μs。对于不会挂起的短任务，这条路径
 * 把吞吐提升了一个数量级。
 */
final class Coroutine
{
    private static int $counter = 0;

    /**
     * 承载本协程执行的 Fiber（由调度器在启动时绑定，结束后解绑）
     */
    private ?Fiber $fiber = null;

    private readonly Closure $task;

    private CoroutineState $state = CoroutineState::Pending;

    private mixed $result = null;

    private ?Throwable $error = null;

    /**
     * @var Suspension[] 等待本协程结束的其它协程
     */
    private array $joiners = [];

    /**
     * 当前挂起句柄（用于取消时把异常注入挂起点）
     */
    private ?Suspension $suspension = null;

    /**
     * 结果或异常是否已被消费
     */
    private bool $handled = false;

    /**
     * 是否收到过取消请求
     */
    private bool $cancelRequested = false;

    /**
     * 自动生成的序号（仅在真正需要 id 时才拼成字符串）
     */
    private readonly int $sequence;

    /**
     * 惰性求值的标识符缓存
     */
    private ?string $id = null;

    public function __construct(
        Closure $task,
        private readonly Scheduler $scheduler,
        ?string $name = null,
    ) {
        $this->sequence = ++self::$counter;
        $this->id = $name;
        $this->task = $task;
    }

    /**
     * 协程标识（未显式命名时形如 co#123）
     *
     * 标识只在调试输出与异常信息里用得到，却要为每个协程做一次字符串拼接与
     * 分配。高并发下这是纯浪费，因此推迟到首次访问时才生成。
     */
    public function id(): string
    {
        return $this->id ??= 'co#' . $this->sequence;
    }

    /**
     * 兼容以属性方式读取 $coroutine->id
     */
    public function __get(string $property): mixed
    {
        if ($property === 'id') {
            return $this->id();
        }

        trigger_error(
            sprintf('未定义的属性 %s::$%s', self::class, $property),
            E_USER_WARNING
        );

        return null;
    }

    /**
     * 当前承载执行的 Fiber（未启动或已结束时为 null）
     *
     * @internal 仅供 Scheduler 登记归属关系
     */
    public function fiber(): ?Fiber
    {
        return $this->fiber;
    }

    /**
     * 启动前置检查：返回 false 表示无需分配执行载体
     *
     * 已取消或已结束的协程在这里直接收尾，避免白白从池里借一个 Fiber。
     *
     * @internal 仅供 Scheduler 调用
     */
    public function prepareStart(): bool
    {
        if ($this->state !== CoroutineState::Pending) {
            return false;
        }

        if ($this->cancelRequested) {
            $this->state = CoroutineState::Cancelled;
            $this->error = new CancelledException(sprintf('协程 %s 在启动前已被取消', $this->id()));
            $this->wakeJoiners();

            return false;
        }

        return true;
    }

    /**
     * 在借来的 Fiber 内执行协程主体
     *
     * 本方法保证「绝不向外抛出异常」——它运行在复用池的 worker Fiber 里，
     * 一旦异常逃逸就会连带终止整个 worker，让池子越用越小。任何失败都被
     * 归档到协程自身的 error 上，由 join()/result() 消费。
     *
     * 载体绑定也在这里完成：worker 恢复后紧接着就是本方法，中间不会有任何
     * 其它 PHP 代码观察到状态，因此不必再单开一次 bindFiber() 调用。
     *
     * @internal 仅供 Fiber 复用池的 worker 循环调用
     */
    public function execute(Fiber $fiber): void
    {
        $this->fiber = $fiber;
        $this->state = CoroutineState::Running;

        try {
            $this->result = ($this->task)();
            $this->state = CoroutineState::Completed;
        } catch (CancelledException $e) {
            $this->error = $e;
            $this->state = CoroutineState::Cancelled;
        } catch (Throwable $e) {
            $this->error = $e;
            $this->state = CoroutineState::Failed;
        } finally {
            $this->suspension = null;
            $this->fiber = null;

            // 收尾路径按「常见情况零成本」组织：绝大多数协程既无 join 者也不会
            // 失败，此时两个分支都不进，省下一次数组拷贝与一次跨类方法调用。
            // Fiber 归属登记的摘除由调度器的 worker 循环就地完成，无需回调。
            if ($this->joiners !== []) {
                $joiners = $this->joiners;
                $this->joiners = [];

                foreach ($joiners as $joiner) {
                    $joiner->resume();
                }
            }

            if ($this->error !== null) {
                $this->scheduler->onCoroutineFinished($this);
            }
        }
    }

    /**
     * 记录当前挂起句柄
     *
     * @internal 仅供 Scheduler 调用
     */
    public function setSuspension(?Suspension $suspension): void
    {
        $this->suspension = $suspension;
    }

    /**
     * 等待协程结束并返回结果
     *
     * 在协程内部调用会挂起当前协程；在协程外部调用会驱动调度器直到本协程结束。
     *
     * @throws Throwable 协程执行期间抛出的异常
     */
    public function join(?float $timeout = null): mixed
    {
        if (!$this->state->isFinished()) {
            $currentFiber = Fiber::getCurrent();

            if ($currentFiber !== null && $this->scheduler->ownsFiber($currentFiber)) {
                $suspension = $this->scheduler->park();
                $this->joiners[] = $suspension;

                $timer = null;
                if ($timeout !== null) {
                    $timer = $this->scheduler->delay(
                        $timeout,
                        fn() => $suspension->throw(new TimeoutException(
                            sprintf('等待协程 %s 超时（%.3fs）', $this->id(), $timeout)
                        ))
                    );
                }

                try {
                    $suspension->suspend();
                } finally {
                    $timer?->cancel();
                }
            } else {
                $deadline = $timeout === null ? null : Scheduler::now() + $timeout;
                $this->scheduler->run(
                    fn(): bool => $this->state->isFinished()
                        || ($deadline !== null && Scheduler::now() >= $deadline)
                );

                if (!$this->state->isFinished()) {
                    if ($timeout !== null) {
                        throw new TimeoutException(
                            sprintf('等待协程 %s 超时（%.3fs）', $this->id(), $timeout)
                        );
                    }

                    // 事件循环已无任何可执行任务，但协程仍未结束：只可能是相互等待造成的死锁
                    throw new DeadlockException(
                        sprintf('等待协程 %s 时事件循环已无可执行任务，可能存在死锁', $this->id())
                    );
                }
            }
        }

        return $this->result();
    }

    /**
     * 获取执行结果（协程必须已结束）
     *
     * @throws Throwable
     */
    public function result(): mixed
    {
        $this->handled = true;

        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->result;
    }

    /**
     * 请求取消协程
     *
     * 若协程尚未启动则不会执行；若正挂起于某个等待点，则在该点抛出
     * {@see CancelledException}；若正在运行，仅置位标记，由
     * {@see self::throwIfCancelled()} 或下一个挂起点感知。
     */
    public function cancel(?string $reason = null): void
    {
        if ($this->state->isFinished()) {
            return;
        }

        $this->cancelRequested = true;
        $this->scheduler->noteCancelRequest();

        $exception = new CancelledException(
            $reason ?? sprintf('协程 %s 已被取消', $this->id())
        );

        $this->suspension?->throw($exception);
    }

    /**
     * 是否收到过取消请求
     */
    public function isCancelRequested(): bool
    {
        return $this->cancelRequested;
    }

    /**
     * 若已收到取消请求则立即抛出异常（供长循环任务主动检查）
     *
     * @throws CancelledException
     */
    public function throwIfCancelled(): void
    {
        if ($this->cancelRequested && !$this->state->isFinished()) {
            throw new CancelledException(sprintf('协程 %s 已被取消', $this->id()));
        }
    }

    public function state(): CoroutineState
    {
        return $this->state;
    }

    public function isFinished(): bool
    {
        return $this->state->isFinished();
    }

    public function isSuccessful(): bool
    {
        return $this->state === CoroutineState::Completed;
    }

    /**
     * 获取失败原因（未失败时为 null）
     */
    public function error(): ?Throwable
    {
        return $this->error;
    }

    /**
     * 异常是否已被 join()/result() 消费
     *
     * @internal 供 Scheduler 统计未处理异常
     */
    public function isHandled(): bool
    {
        return $this->handled;
    }

    private function wakeJoiners(): void
    {
        $joiners = $this->joiners;
        $this->joiners = [];

        foreach ($joiners as $joiner) {
            $joiner->resume();
        }

        $this->scheduler->onCoroutineFinished($this);
    }
}
