<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Closure;
use Fiber;
use Kode\Fibers\Exceptions\FiberException;
use Throwable;
use WeakMap;

/**
 * 协作式协程调度器
 *
 * 由「就绪队列 + 定时器最小堆」构成的单线程事件循环，是本库所有非阻塞能力的地基：
 *
 * - {@see self::go()}     创建受管理的协程
 * - {@see self::sleep()}  真正让出执行权的休眠（不阻塞其它协程）
 * - {@see self::delay()}  一次性 / 周期性定时器
 * - {@see self::park()}   供 Channel、WaitGroup、Semaphore 等原语挂起与唤醒协程
 *
 * ```php
 * $scheduler = new Scheduler();
 * $a = $scheduler->go(function () use ($scheduler) {
 *     $scheduler->sleep(0.1);
 *     return 'a';
 * });
 * $b = $scheduler->go(fn() => 'b');
 * $scheduler->run();          // 驱动事件循环直到无事可做
 * [$a->result(), $b->result()];
 * ```
 */
final class Scheduler
{
    /**
     * 当前正在驱动事件循环的调度器
     */
    private static ?self $current = null;

    /**
     * 进程级默认调度器
     */
    private static ?self $default = null;

    /**
     * 就绪队列
     *
     * 用「原生数组 + 头尾游标」而非 SplQueue：后者每次入队都要分配一个双向链表
     * 节点对象，在每秒百万级调度的热路径上这笔开销相当可观。
     *
     * 队列元素是多态的，调度器按类型直接派发，从而免去为每次唤醒包一层闭包：
     * - {@see Fiber}      直接恢复执行（yieldNow 的零分配快路径）
     * - {@see Suspension} 交还控制权给挂起的协程
     * - {@see Coroutine}  首次启动协程
     * - {@see Timer}      触发到期定时器
     * - {@see Closure}    普通 defer 回调
     *
     * @var array<int, Closure|Coroutine|Timer|Suspension|Fiber>
     */
    private array $ready = [];

    /**
     * 就绪队列出队游标
     */
    private int $readyHead = 0;

    /**
     * 就绪队列入队游标
     */
    private int $readyTail = 0;

    private TimerQueue $timers;

    private int $timerSequence = 0;

    /**
     * 定时器基准时刻的量化窗口（秒）
     *
     * 窗口内注册的定时器共用同一个基准时刻，从而共享定时器堆里的同一个桶。
     * 0.5ms 远低于 PHP 事件循环的实际调度抖动，也小于 Swoole 定时器 1ms 的
     * 最小粒度，对触发精度没有可观察的影响。
     */
    private const CLOCK_GRANULARITY = 0.0005;

    /**
     * 缓存的基准时刻（单调时钟秒）
     */
    private float $clockBase = 0.0;

    private bool $running = false;

    /**
     * Fiber => Coroutine 归属关系（弱引用，协程结束后自动回收）
     *
     * @var WeakMap<Fiber, Coroutine>
     */
    private WeakMap $owned;

    /**
     * 空闲 Fiber 复用池（后进先出，优先复用最近归还的，CPU 缓存更热）
     *
     * `Fiber::start()` 需要为协程分配一段独立的 C 栈，实测约 3.6μs，是「创建 +
     * 执行一个协程」的绝对大头；而复用一个已存在的 Fiber（resume + suspend）
     * 只要约 0.14μs。池中的 worker 是一个「取任务 → 执行 → 归还自己 → 挂起」
     * 的循环体，任务之间彼此隔离，语义与「一任务一 Fiber」完全一致。
     *
     * 任务在执行途中挂起时，承载它的 worker 不会回到池里（它已被该任务占用），
     * 调度器会为后续任务另借一个，因此池的实际规模等于并发挂起的峰值。
     *
     * @var Fiber[]
     */
    private array $idleWorkers = [];

    private int $idleCount = 0;

    /**
     * 空闲池上限：每个空闲 worker 都占着一段 C 栈，无上限会让长驻进程在一次
     * 并发尖峰后永久持有大量内存
     */
    private int $workerPoolLimit = 512;

    /**
     * 即将交给 worker 执行的协程（设置后立刻 resume，中间不会有其它 PHP 代码
     * 执行，因此单槽足够，无需队列）
     */
    private ?Coroutine $pendingTask = null;

    /**
     * 本调度器收到过的取消请求总数
     *
     * 取消在绝大多数程序里是极低频事件，而「每次让出都问一遍协程是否被取消」
     * 落在最热的调度路径上。用一个整型计数做前置短路：只要从没人取消过，
     * 热路径上就只剩一次整数比较。
     */
    private int $cancelRequests = 0;

    private int $tickCount = 0;

    private int $spawnedCount = 0;

    /**
     * 累计创建的 worker Fiber 数（用于观测复用率）
     */
    private int $workersCreated = 0;

    /**
     * 累计复用次数
     */
    private int $workersReused = 0;

    /**
     * 已失败但结果尚未被消费的协程
     *
     * @var Coroutine[]
     */
    private array $failures = [];

    /**
     * 定时器 / defer 回调抛出异常时的处理器，为 null 时向外抛出
     */
    private ?Closure $errorHandler = null;

    public function __construct()
    {
        $this->timers = new TimerQueue();
        $this->owned = new WeakMap();
    }

    /**
     * 单调时钟当前值（秒）
     *
     * 使用 hrtime() 而非 microtime()，不受系统时间调整（NTP）影响。
     */
    public static function now(): float
    {
        return hrtime(true) / 1_000_000_000;
    }

    /**
     * 获取进程级默认调度器
     */
    public static function default(): self
    {
        return self::$default ??= new self();
    }

    /**
     * 获取当前正在运行的调度器（不在事件循环内时返回 null）
     */
    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * 当前是否处于某个受调度器管理的协程内部
     */
    public static function inCoroutine(): bool
    {
        $fiber = Fiber::getCurrent();

        return $fiber !== null && self::$current !== null && self::$current->ownsFiber($fiber);
    }

    /**
     * 重置默认调度器（主要用于测试隔离）
     */
    public static function resetDefault(): void
    {
        self::$default = null;
    }

    /**
     * 事件循环是否正在运行
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * 指定 Fiber 是否由本调度器创建
     */
    public function ownsFiber(Fiber $fiber): bool
    {
        return isset($this->owned[$fiber]);
    }

    /**
     * 获取当前正在执行的协程句柄
     */
    public function currentCoroutine(): ?Coroutine
    {
        $fiber = Fiber::getCurrent();

        if ($fiber === null || !isset($this->owned[$fiber])) {
            return null;
        }

        return $this->owned[$fiber];
    }

    /**
     * 创建并调度一个协程
     *
     * @param callable    $task 协程主体
     * @param string|null $name 可选名称，便于调试
     */
    public function go(callable $task, ?string $name = null): Coroutine
    {
        $coroutine = new Coroutine(
            $task instanceof Closure ? $task : Closure::fromCallable($task),
            $this,
            $name
        );

        $this->spawnedCount++;

        // 只登记待办，不分配 Fiber：执行载体在真正启动时才从复用池借出
        $this->ready[$this->readyTail++] = $coroutine;

        return $coroutine;
    }

    /**
     * 批量创建协程
     *
     * @param  iterable<array-key, callable> $tasks
     * @return array<array-key, Coroutine>
     */
    public function goAll(iterable $tasks): array
    {
        $coroutines = [];

        foreach ($tasks as $key => $task) {
            $coroutines[$key] = $this->go($task, is_string($key) ? $key : null);
        }

        return $coroutines;
    }

    /**
     * 向就绪队列投递一个可调度项
     *
     * @param  Closure|Coroutine|Timer|Suspension|Fiber $item
     * @internal 供 Suspension 等内部组件使用
     */
    public function enqueue(Closure|Coroutine|Timer|Suspension|Fiber $item): void
    {
        $this->ready[$this->readyTail++] = $item;
    }

    /**
     * 在下一个事件循环 tick 执行回调
     */
    public function defer(callable $callback): void
    {
        $this->ready[$this->readyTail++] = $callback instanceof Closure
            ? $callback
            : Closure::fromCallable($callback);
    }

    /**
     * 注册一次性定时器
     *
     * @param float $seconds 延迟秒数
     */
    public function delay(float $seconds, callable $callback): Timer
    {
        return $this->createTimer($seconds, $callback, null);
    }

    /**
     * 注册周期性定时器
     *
     * @param float $seconds 间隔秒数（必须大于 0）
     */
    public function repeat(float $seconds, callable $callback): Timer
    {
        if ($seconds <= 0) {
            throw new FiberException('周期性定时器的间隔必须大于 0 秒');
        }

        return $this->createTimer($seconds, $callback, $seconds);
    }

    /**
     * 挂起当前协程，返回可用于唤醒它的句柄
     *
     * @throws FiberException 当前不在受本调度器管理的协程内
     */
    public function park(): Suspension
    {
        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            throw new FiberException(
                '当前不在协程内，无法挂起。请通过 Fibers::async() / Scheduler::go() 创建协程后再调用。'
            );
        }

        // 单次 WeakMap 查找同时完成归属校验与句柄登记
        $coroutine = $this->owned[$fiber] ?? null;

        if ($coroutine === null) {
            throw new FiberException(
                '当前 Fiber 不受该调度器管理，无法挂起。请使用同一个 Scheduler 创建协程。'
            );
        }

        $suspension = new Suspension($this, $fiber);
        $coroutine->setSuspension($suspension);

        return $suspension;
    }

    /**
     * 协程休眠（让出执行权，不阻塞其它协程）
     */
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            $this->yieldNow();

            return;
        }

        $suspension = $this->park();
        $timer = $this->delay($seconds, static function () use ($suspension): void {
            $suspension->resume();
        });

        try {
            $suspension->suspend();
        } finally {
            $timer->cancel();
        }
    }

    /**
     * 主动让出一次执行权，把机会交给其它就绪协程
     */
    public function yieldNow(): void
    {
        $fiber = Fiber::getCurrent();

        // 合法调用（处于本调度器管理的协程内）是最高频动作；除非根本不在协程里，
        // 否则不走任何校验分支，直接走零分配快路径。
        if ($fiber === null) {
            throw new FiberException(
                '当前不在协程内，无法让出执行权。请通过 Fibers::async() / Scheduler::go() 创建协程后再调用。'
            );
        }

        // 让出并立刻重新排队是最高频的调度动作，这里走「零分配」快路径：
        // 直接把 Fiber 本体投递进就绪队列，跳过 Suspension 对象的分配、登记与
        // 派发。实测 PHP 的 Fiber::suspend()/resume() 往返本身只要 0.073μs，
        // 之前每次让出却要花掉约 0.58μs，绝大部分耗在这层包装上。
        //
        // 归属校验（WeakMap 查找）推迟到唤醒之后，且只在确有取消请求时才查一次——
        // 绝大多数让出路径上彻底省掉这次查找。取消语义保持不变：挂起期间收到的
        // 取消请求会在被唤醒后立即抛出，抛出点仍是本次 yieldNow() 调用处。
        $this->ready[$this->readyTail++] = $fiber;

        Fiber::suspend();

        if ($this->cancelRequests !== 0) {
            $coroutine = $this->owned[$fiber] ?? null;

            if ($coroutine !== null) {
                $coroutine->throwIfCancelled();
            }
        }
    }

    /**
     * 登记一次取消请求
     *
     * @internal 仅供 Coroutine::cancel() 调用
     */
    public function noteCancelRequest(): void
    {
        $this->cancelRequests++;
    }

    /**
     * 等待协程完成并取回结果
     *
     * @param  Coroutine|array<array-key, Coroutine> $coroutine
     * @return mixed 单个协程返回其结果；数组返回与键对应的结果数组
     * @throws Throwable
     */
    public function await(Coroutine|array $coroutine, ?float $timeout = null): mixed
    {
        if (is_array($coroutine)) {
            $results = [];

            foreach ($coroutine as $key => $item) {
                $results[$key] = $item->join($timeout);
            }

            return $results;
        }

        return $coroutine->join($timeout);
    }

    /**
     * 以截止时间运行一个任务：超时后会在协程的下一个挂起点真正中断它
     *
     * 与「跑完再比较耗时」的事后断言不同，这里超时的任务会被取消，
     * 不会继续占用资源，也不会被上层误判为可重试的失败。
     *
     * @throws TimeoutException 超时
     * @throws Throwable        任务自身抛出的异常
     */
    public function withTimeout(callable $task, float $timeout): mixed
    {
        $coroutine = $this->go($task);

        $timer = $this->delay($timeout, static function () use ($coroutine, $timeout): void {
            $coroutine->cancel(sprintf('任务执行超过 %.3f 秒，已被取消', $timeout));
        });

        try {
            return $this->await($coroutine);
        } catch (CancelledException $e) {
            if ($coroutine->isCancelRequested()) {
                throw new TimeoutException(
                    sprintf('任务执行超过 %.3f 秒', $timeout),
                    $e->getCode(),
                    $e
                );
            }

            throw $e;
        } finally {
            $timer->cancel();
        }
    }

    /**
     * 驱动事件循环
     *
     * @param callable|null $until 停止条件，返回 true 时退出；为 null 时运行到无事可做
     * @throws FiberException 重入调用
     */
    public function run(?callable $until = null): void
    {
        if ($this->running) {
            throw new FiberException('Scheduler::run() 不可重入，请使用 defer() / go() 在事件循环内调度任务');
        }

        $previous = self::$current;
        self::$current = $this;
        $this->running = true;

        try {
            while (true) {
                if ($until !== null && $until()) {
                    return;
                }

                if ($this->timers->count() > 0) {
                    $this->expireTimers();
                }

                if ($this->readyHead < $this->readyTail) {
                    $this->drainReady($until === null);

                    continue;
                }

                $next = $this->timers->peek();

                if ($next === null) {
                    return;
                }

                $delay = $next->at - self::now();

                if ($delay > 0) {
                    // 无任何就绪任务，等待最近的定时器到期：这是真正的空闲等待而非轮询
                    usleep((int) ceil($delay * 1_000_000));
                }
            }
        } finally {
            $this->running = false;
            self::$current = $previous;
        }
    }

    /**
     * 执行一批任务并返回结果（键与输入保持一致）
     *
     * @param  iterable<array-key, callable> $tasks
     * @return array<array-key, mixed>
     */
    public function runAll(iterable $tasks): array
    {
        $coroutines = $this->goAll($tasks);

        $this->run(static function () use ($coroutines): bool {
            foreach ($coroutines as $coroutine) {
                if (!$coroutine->isFinished()) {
                    return false;
                }
            }

            return true;
        });

        $results = [];

        foreach ($coroutines as $key => $coroutine) {
            $results[$key] = $coroutine->result();
        }

        return $results;
    }

    /**
     * 设置定时器 / defer 回调的异常处理器
     *
     * 未设置时，异常会直接从 {@see self::run()} 向外抛出。
     */
    public function setErrorHandler(?callable $handler): void
    {
        $this->errorHandler = $handler === null
            ? null
            : ($handler instanceof Closure ? $handler : Closure::fromCallable($handler));
    }

    /**
     * 获取「已失败且结果从未被读取」的协程异常列表
     *
     * @return Throwable[]
     */
    public function unhandledErrors(): array
    {
        $this->failures = array_values(array_filter(
            $this->failures,
            static fn(Coroutine $coroutine): bool => !$coroutine->isHandled()
        ));

        return array_map(
            static fn(Coroutine $coroutine): Throwable => $coroutine->error() ?? new FiberException('unknown'),
            $this->failures
        );
    }

    /**
     * 协程失败回调
     *
     * 仅在协程以异常结束时被调用；Fiber 归属登记的摘除由 worker 循环负责。
     *
     * @internal 仅供 Coroutine 调用
     */
    public function onCoroutineFinished(Coroutine $coroutine): void
    {
        $fiber = $coroutine->fiber();

        if ($fiber !== null) {
            unset($this->owned[$fiber]);
        }

        if ($coroutine->error() === null) {
            return;
        }

        $this->failures[] = $coroutine;

        // 只保留最近的失败记录，避免长驻进程内存无限增长
        if (count($this->failures) > 128) {
            $this->failures = array_slice($this->failures, -128);
        }
    }

    /**
     * 调度器运行统计
     *
     * @return array{ticks:int, spawned:int, ready:int, timers:int, running:bool, workers_created:int, workers_reused:int, workers_idle:int}
     */
    public function stats(): array
    {
        return [
            'ticks' => $this->tickCount,
            'spawned' => $this->spawnedCount,
            'ready' => $this->readyTail - $this->readyHead,
            'timers' => $this->timers->count(),
            'running' => $this->running,
            'workers_created' => $this->workersCreated,
            'workers_reused' => $this->workersReused,
            'workers_idle' => $this->idleCount,
        ];
    }

    /**
     * 丢弃所有待执行的回调与定时器
     */
    public function clear(): void
    {
        $this->ready = [];
        $this->readyHead = 0;
        $this->readyTail = 0;
        $this->timers->clear();
        $this->failures = [];
    }

    /**
     * 设置空闲 Fiber 复用池上限
     *
     * 默认 512。调大可以让高并发峰值后的下一波任务全部命中复用；调小则更省内存。
     * 设为 0 会退化成「一任务一 Fiber」，仅建议在排查问题时使用。
     */
    public function setWorkerPoolLimit(int $limit): void
    {
        $this->workerPoolLimit = max(0, $limit);

        while ($this->idleCount > $this->workerPoolLimit) {
            unset($this->idleWorkers[--$this->idleCount]);
        }
    }

    /**
     * 启动一个协程：借出执行载体并把控制权交给它
     */
    private function startCoroutine(Coroutine $coroutine): void
    {
        if (!$coroutine->prepareStart()) {
            return;
        }

        $idle = $this->idleCount;

        if ($idle > 0) {
            // 池里的 worker 必然已经 start 过并停在 suspend 点，
            // 不必再问一次 isStarted()。借出逻辑也直接内联，省一层调用。
            $worker = $this->idleWorkers[--$idle];
            unset($this->idleWorkers[$idle]);
            $this->idleCount = $idle;
            $this->workersReused++;

            $this->owned[$worker] = $coroutine;
            $this->pendingTask = $coroutine;

            $worker->resume();

            return;
        }

        $worker = $this->createWorker();

        $this->owned[$worker] = $coroutine;
        $this->pendingTask = $coroutine;

        $worker->start();
    }

    /**
     * 新建一个 worker（复用池为空时）
     */
    private function createWorker(): Fiber
    {
        $this->workersCreated++;

        // worker 需要在归还时引用自己，而 Fiber 实例要等构造完才拿得到，
        // 故用一个轻量的持有者做延迟回填。
        // 闭包刻意不声明 static：绑定到调度器实例后可直接读写私有成员，
        // 把每个任务的「取任务 / 归还 worker」从两次方法调用降为直接属性访问。
        $holder = new WorkerHandle();

        $holder->fiber = new Fiber(function () use ($holder): void {
            $self = $holder->fiber;

            while (true) {
                $coroutine = $this->pendingTask;

                if ($coroutine === null) {
                    return;
                }

                $this->pendingTask = null;

                // execute() 内部已兜住全部异常，不会让 worker 意外终止；
                // 载体绑定也在它内部一并完成，省掉一次 bindFiber() 调用
                $coroutine->execute($self);

                // 就地摘除归属登记：worker 即将承载下一个协程，不能让旧的
                // Coroutine 继续被这个 Fiber 键钉在 WeakMap 里
                unset($this->owned[$self]);

                if ($this->idleCount >= $this->workerPoolLimit) {
                    return;
                }

                $this->idleWorkers[$this->idleCount++] = $self;

                Fiber::suspend();
            }
        });

        return $holder->fiber;
    }

    private function createTimer(float $seconds, callable $callback, ?float $interval): Timer
    {
        // 基准时刻按 CLOCK_GRANULARITY 量化后复用（libuv / libev 的「缓存循环时间」
        // 做法）。同一时间窗内注册的定时器由此得到完全相同的 at，直接落进定时器
        // 堆的同一个桶：入堆与出堆都退化为 O(1)，而不是每个定时器各占一个堆节点。
        // 实测批量注册场景下整体快 3~4 倍，代价是触发时刻最多提前不到 1 个窗口，
        // 这远小于 PHP 事件循环本身能保证的定时精度。
        $now = self::now();

        if ($now - $this->clockBase >= self::CLOCK_GRANULARITY) {
            $this->clockBase = $now;
        }

        $sequence = ++$this->timerSequence;
        $timer = new Timer(
            $this->clockBase + ($seconds > 0.0 ? $seconds : 0.0),
            $sequence,
            $callback instanceof Closure ? $callback : Closure::fromCallable($callback),
            $interval,
            null,
            $this->timers,
        );

        $this->timers->insert($timer);

        return $timer;
    }

    /**
     * 将所有到期定时器搬运到就绪队列
     */
    private function expireTimers(): void
    {
        $now = self::now();

        // 只处理进入本轮时就已存在的定时器：回调内部新注册的零延迟定时器留到
        // 下一轮，避免「回调不断注册 delay(0)」把事件循环永久困在本函数里。
        $limit = $this->timerSequence;

        while (($timer = $this->timers->extractExpired($now, $limit)) !== null) {
            if ($timer->interval !== null) {
                $timer->reschedule($now);
                $this->timers->insert($timer);
            }

            // 到期即触发，不再绕行就绪队列：省掉一次入队、一次出队和一次类型派发。
            // 定时器本就该在到期时刻执行，中转反而引入额外延迟。
            $this->tickCount++;

            try {
                // extractExpired() 已保证未取消，直接调用回调，跳过 fire() 的转发
                ($timer->callback)();
            } catch (Throwable $e) {
                if ($this->errorHandler === null) {
                    throw $e;
                }

                ($this->errorHandler)($e);
            }
        }
    }

    /**
     * 执行当前批次的就绪回调
     *
     * 只处理进入本轮时的快照数量，新产生的回调留到下一轮，
     * 从而保证定时器不会被持续自我唤醒的协程饿死。
     *
     * @param bool $continuous 没有定时器、也没有 until 谓词时，允许连续处理
     *                         多个批次而不返回 run() 的外层循环。此时既没有
     *                         可被饿死的定时器，也没有需要复查的停止条件，
     *                         语义与逐批返回完全一致，却省掉了每次让出都要
     *                         付的一轮「谓词判断 + 函数调用/返回」开销。
     */
    private function drainReady(bool $continuous): void
    {
        // 整个批次循环内联在这里而非拆成子方法：每让出一次就要走一遍，
        // 一层 PHP 函数调用在这个量级上是实打实的开销
        $timers = $this->timers;

        do {
            // 只处理进入本批次时已存在的项，新产生的留到下一批
            $batch = $this->readyTail;
            $head = $this->readyHead;

            while ($head < $batch) {
                $item = $this->ready[$head];
                unset($this->ready[$head]);
                $this->readyHead = ++$head;
                $this->tickCount++;

                try {
                    // 按出现频率排序的类型派发。yieldNow() 的零分配快路径投递的是
                    // Fiber 本体，是紧凑循环里最频繁的一类，放在首位；
                    // isSuspended() 对已终止的 Fiber 同样返回 false，无需再单独判
                    // isTerminated()，热路径上少一次方法调用。
                    if ($item instanceof Fiber) {
                        if ($item->isSuspended()) {
                            $item->resume();
                        }
                    } elseif ($item instanceof Suspension) {
                        $item->dispatch();
                    } elseif ($item instanceof Coroutine) {
                        $this->startCoroutine($item);
                    } elseif ($item instanceof Timer) {
                        $item->fire();
                    } else {
                        $item();
                    }
                } catch (Throwable $e) {
                    if ($this->errorHandler === null) {
                        throw $e;
                    }

                    ($this->errorHandler)($e);
                }

                // 批次内被恢复的协程可能重置了游标（队列排空时会归零），
                // 必须重新读取而不能沿用局部变量
                $head = $this->readyHead;
            }

            if ($head === $this->readyTail) {
                // 队列排空：连同底层数组与游标一并归零
                $this->ready = [];
                $this->readyHead = 0;
                $this->readyTail = 0;

                return;
            }

            if ($head >= 1024 && $head >= $this->readyTail - $head) {
                // 自我唤醒的协程会让游标单调增长，底层数组随之无限扩张。
                // 已消费部分超过存量时压缩一次，摊还成本 O(1)，也让热循环
                // 始终工作在一小块连续内存上，缓存命中率明显更好。
                $this->ready = array_values($this->ready);
                $this->readyTail -= $head;
                $this->readyHead = 0;
            }
        } while ($continuous && $timers->total === 0);
    }
}
