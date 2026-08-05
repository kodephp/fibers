<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Fiber;

/**
 * worker Fiber 的自引用持有者
 *
 * worker 的循环体需要在执行完任务后把「自己」归还给复用池，但 Fiber 实例要等
 * `new Fiber(...)` 返回后才存在，闭包在构造时拿不到。用这个极轻量的容器做一次
 * 延迟回填，比 `static` 变量或数组引用更省一次哈希查找。
 *
 * @internal 仅供 {@see Scheduler} 的 Fiber 复用池使用
 */
final class WorkerHandle
{
    public ?Fiber $fiber = null;
}
