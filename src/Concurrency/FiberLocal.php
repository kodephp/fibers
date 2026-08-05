<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Closure;
use Fiber;
use WeakMap;

/**
 * 协程本地存储（Fiber-Local Storage）
 *
 * 每个协程拥有独立副本，互不串扰；协程结束后由 WeakMap 自动回收。
 * 用于替代「全局静态数组 + Context::clear()」这种会被并发协程互相破坏的做法。
 *
 * ```php
 * $traceId = new FiberLocal(fn() => bin2hex(random_bytes(8)));
 *
 * $scheduler->go(fn() => $traceId->get());   // 协程 A 的 traceId
 * $scheduler->go(fn() => $traceId->get());   // 协程 B 的 traceId（不同值）
 * ```
 *
 * @template T
 */
final class FiberLocal
{
    /**
     * 执行上下文 => 值
     *
     * 键优先取「协程句柄」而非 Fiber：调度器会复用 Fiber（见 Scheduler 的复用
     * 池），同一个 Fiber 先后承载多个互不相关的协程，若以 Fiber 为键，前一个
     * 协程写入的值会泄漏给后一个。以协程为键则天然随协程结束一起回收。
     * 不在受管协程内（裸 Fiber）时退回按 Fiber 隔离。
     *
     * @var WeakMap<object, array{0: mixed}>
     */
    private WeakMap $storage;

    /**
     * 主协程（非 Fiber 上下文）的值
     *
     * @var array{0: mixed}|null
     */
    private ?array $mainValue = null;

    /**
     * @param Closure|null $initializer 首次访问时生成默认值
     */
    public function __construct(private readonly ?Closure $initializer = null)
    {
        $this->storage = new WeakMap();
    }

    /**
     * 读取当前协程的值
     *
     * @return T|mixed
     */
    public function get(): mixed
    {
        $context = self::context();

        if ($context === null) {
            if ($this->mainValue === null) {
                $this->mainValue = [$this->initialize()];
            }

            return $this->mainValue[0];
        }

        if (!isset($this->storage[$context])) {
            $this->storage[$context] = [$this->initialize()];
        }

        return $this->storage[$context][0];
    }

    /**
     * 写入当前协程的值
     */
    public function set(mixed $value): void
    {
        $context = self::context();

        if ($context === null) {
            $this->mainValue = [$value];

            return;
        }

        $this->storage[$context] = [$value];
    }

    /**
     * 当前协程是否已有值
     */
    public function has(): bool
    {
        $context = self::context();

        return $context === null
            ? $this->mainValue !== null
            : isset($this->storage[$context]);
    }

    /**
     * 清除当前协程的值（下次 get() 会重新初始化）
     */
    public function unset(): void
    {
        $context = self::context();

        if ($context === null) {
            $this->mainValue = null;

            return;
        }

        unset($this->storage[$context]);
    }

    /**
     * 当前执行上下文：受管协程优先，其次是裸 Fiber，主协程为 null
     */
    private static function context(): ?object
    {
        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return null;
        }

        return Scheduler::current()?->currentCoroutine() ?? $fiber;
    }

    /**
     * 在当前协程内临时替换值并执行任务，结束后恢复原值
     */
    public function with(mixed $value, callable $task): mixed
    {
        $hadValue = $this->has();
        $previous = $hadValue ? $this->get() : null;

        $this->set($value);

        try {
            return $task();
        } finally {
            if ($hadValue) {
                $this->set($previous);
            } else {
                $this->unset();
            }
        }
    }

    private function initialize(): mixed
    {
        return $this->initializer === null ? null : ($this->initializer)();
    }
}
