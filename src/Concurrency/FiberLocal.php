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
     * Fiber => 值
     *
     * @var WeakMap<Fiber, array{0: mixed}>
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
        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            if ($this->mainValue === null) {
                $this->mainValue = [$this->initialize()];
            }

            return $this->mainValue[0];
        }

        if (!isset($this->storage[$fiber])) {
            $this->storage[$fiber] = [$this->initialize()];
        }

        return $this->storage[$fiber][0];
    }

    /**
     * 写入当前协程的值
     */
    public function set(mixed $value): void
    {
        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            $this->mainValue = [$value];

            return;
        }

        $this->storage[$fiber] = [$value];
    }

    /**
     * 当前协程是否已有值
     */
    public function has(): bool
    {
        $fiber = Fiber::getCurrent();

        return $fiber === null
            ? $this->mainValue !== null
            : isset($this->storage[$fiber]);
    }

    /**
     * 清除当前协程的值（下次 get() 会重新初始化）
     */
    public function unset(): void
    {
        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            $this->mainValue = null;

            return;
        }

        unset($this->storage[$fiber]);
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
