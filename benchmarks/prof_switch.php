<?php

declare(strict_types=1);

// 逐层剥离协程切换开销：从裸 Fiber 往上加，看每一层各吃掉多少
$n = 200_000;

function bench(string $label, callable $fn, int $n): void
{
    $best = PHP_FLOAT_MAX;

    for ($r = 0; $r < 5; $r++) {
        $t = hrtime(true);
        $fn();
        $d = (hrtime(true) - $t) / 1e9;
        $best = min($best, $d);
    }

    printf("%-52s %10.3f ms  %12s ops/s  %7.4f us/op\n", $label, $best * 1000, number_format($n / $best), $best / $n * 1e6);
}

// L0：裸 Fiber 往返（物理下限）
bench('L0 裸 Fiber suspend/resume', static function () use ($n): void {
    $fiber = new Fiber(static function () use ($n): void {
        for ($i = 0; $i < $n; $i++) {
            Fiber::suspend();
        }
    });
    $fiber->start();

    for ($i = 0; $i < $n; $i++) {
        $fiber->resume();
    }
}, $n);

// L1：+ 就绪队列（数组游标）
bench('L1 + 就绪队列游标', static function () use ($n): void {
    $ready = [];
    $head = 0;
    $tail = 0;

    $fiber = new Fiber(static function () use ($n, &$ready, &$tail): void {
        $self = Fiber::getCurrent();

        for ($i = 0; $i < $n; $i++) {
            $ready[$tail++] = $self;
            Fiber::suspend();
        }
    });
    $ready[$tail++] = $fiber;

    while ($head < $tail) {
        $item = $ready[$head];
        unset($ready[$head]);
        $head++;

        if ($item->isSuspended()) {
            $item->resume();
        } else {
            $item->start();
        }
    }
}, $n);

// L2：+ WeakMap 归属查找
bench('L2 + WeakMap 归属查找', static function () use ($n): void {
    $ready = [];
    $head = 0;
    $tail = 0;
    $owned = new WeakMap();

    $fiber = new Fiber(static function () use ($n, &$ready, &$tail, $owned): void {
        $self = Fiber::getCurrent();

        for ($i = 0; $i < $n; $i++) {
            $co = $owned[$self] ?? null;
            $ready[$tail++] = $self;
            Fiber::suspend();
        }
    });
    $owned[$fiber] = new stdClass();
    $ready[$tail++] = $fiber;

    while ($head < $tail) {
        $item = $ready[$head];
        unset($ready[$head]);
        $head++;

        if ($item->isSuspended()) {
            $item->resume();
        } else {
            $item->start();
        }
    }
}, $n);

// L3：+ try/catch + instanceof 派发链 + 计数器
bench('L3 + try/catch + instanceof 链 + tickCount', static function () use ($n): void {
    $ready = [];
    $head = 0;
    $tail = 0;
    $ticks = 0;
    $owned = new WeakMap();

    $fiber = new Fiber(static function () use ($n, &$ready, &$tail, $owned): void {
        $self = Fiber::getCurrent();

        for ($i = 0; $i < $n; $i++) {
            $co = $owned[$self] ?? null;
            $ready[$tail++] = $self;
            Fiber::suspend();
        }
    });
    $owned[$fiber] = new stdClass();
    $ready[$tail++] = $fiber;

    while ($head < $tail) {
        $item = $ready[$head];
        unset($ready[$head]);
        $head++;
        $ticks++;

        try {
            if ($item instanceof Fiber) {
                if ($item->isSuspended()) {
                    $item->resume();
                } else {
                    $item->start();
                }
            } else {
                $item();
            }
        } catch (Throwable $e) {
            throw $e;
        }
    }
}, $n);

// L4：+ 方法调用层（yieldNow 作为对象方法）
final class MiniScheduler
{
    public array $ready = [];

    public int $head = 0;

    public int $tail = 0;

    public int $ticks = 0;

    public WeakMap $owned;

    public function __construct()
    {
        $this->owned = new WeakMap();
    }

    public function yieldNow(): void
    {
        $fiber = Fiber::getCurrent();
        $co = $this->owned[$fiber] ?? null;

        if ($co === null) {
            throw new RuntimeException('unowned');
        }

        $this->ready[$this->tail++] = $fiber;

        Fiber::suspend();
    }

    public function run(): void
    {
        while ($this->head < $this->tail) {
            $this->drain();
        }
    }

    private function drain(): void
    {
        $batch = $this->tail;

        while ($this->head < $batch) {
            $item = $this->ready[$this->head];
            unset($this->ready[$this->head]);
            $this->head++;
            $this->ticks++;

            try {
                if ($item instanceof Fiber) {
                    if ($item->isSuspended()) {
                        $item->resume();
                    } else {
                        $item->start();
                    }
                } else {
                    $item();
                }
            } catch (Throwable $e) {
                throw $e;
            }
        }
    }
}

bench('L4 + 对象方法层（MiniScheduler）', static function () use ($n): void {
    $s = new MiniScheduler();
    $fiber = new Fiber(static function () use ($n, $s): void {
        for ($i = 0; $i < $n; $i++) {
            $s->yieldNow();
        }
    });
    $s->owned[$fiber] = new stdClass();
    $s->ready[$s->tail++] = $fiber;
    $s->run();
}, $n);

// L4a：yieldNow 不调用 Fiber::getCurrent()，改由调度循环回填字段
final class MiniSchedulerNoGetCurrent
{
    public array $ready = [];

    public int $head = 0;

    public int $tail = 0;

    public int $ticks = 0;

    public ?Fiber $running = null;

    public WeakMap $owned;

    public function __construct()
    {
        $this->owned = new WeakMap();
    }

    public function yieldNow(): void
    {
        $fiber = $this->running;
        $co = $this->owned[$fiber] ?? null;

        if ($co === null) {
            throw new RuntimeException('unowned');
        }

        $this->ready[$this->tail++] = $fiber;

        Fiber::suspend();
    }

    public function run(): void
    {
        while ($this->head < $this->tail) {
            $batch = $this->tail;

            while ($this->head < $batch) {
                $item = $this->ready[$this->head];
                unset($this->ready[$this->head]);
                $this->head++;
                $this->ticks++;

                try {
                    if ($item instanceof Fiber) {
                        $this->running = $item;

                        if ($item->isSuspended()) {
                            $item->resume();
                        } else {
                            $item->start();
                        }
                    } else {
                        $item();
                    }
                } catch (Throwable $e) {
                    throw $e;
                }
            }
        }
    }
}

bench('L4a 同上但不调用 Fiber::getCurrent()', static function () use ($n): void {
    $s = new MiniSchedulerNoGetCurrent();
    $fiber = new Fiber(static function () use ($n, $s): void {
        for ($i = 0; $i < $n; $i++) {
            $s->yieldNow();
        }
    });
    $s->owned[$fiber] = new stdClass();
    $s->ready[$s->tail++] = $fiber;
    $s->run();
}, $n);

// L4b：既不 getCurrent 也不查 WeakMap
final class MiniSchedulerLean
{
    public array $ready = [];

    public int $head = 0;

    public int $tail = 0;

    public int $ticks = 0;

    public ?Fiber $running = null;

    public function yieldNow(): void
    {
        $this->ready[$this->tail++] = $this->running;

        Fiber::suspend();
    }

    public function run(): void
    {
        while ($this->head < $this->tail) {
            $batch = $this->tail;

            while ($this->head < $batch) {
                $item = $this->ready[$this->head];
                unset($this->ready[$this->head]);
                $this->head++;
                $this->ticks++;

                try {
                    if ($item instanceof Fiber) {
                        $this->running = $item;

                        if ($item->isSuspended()) {
                            $item->resume();
                        } else {
                            $item->start();
                        }
                    } else {
                        $item();
                    }
                } catch (Throwable $e) {
                    throw $e;
                }
            }
        }
    }
}

bench('L4b 同上再去掉 WeakMap 查找', static function () use ($n): void {
    $s = new MiniSchedulerLean();
    $fiber = new Fiber(static function () use ($n, $s): void {
        for ($i = 0; $i < $n; $i++) {
            $s->yieldNow();
        }
    });
    $s->ready[$s->tail++] = $fiber;
    $s->run();
}, $n);

// L5：真实 Scheduler
require __DIR__ . '/../vendor/autoload.php';

bench('L5 kode/fibers Scheduler', static function () use ($n): void {
    $scheduler = new Kode\Fibers\Concurrency\Scheduler();
    $scheduler->go(static function () use ($scheduler, $n): void {
        for ($i = 0; $i < $n; $i++) {
            $scheduler->yieldNow();
        }
    });
    $scheduler->run();
}, $n);
