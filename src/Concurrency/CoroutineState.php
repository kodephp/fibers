<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

/**
 * 协程生命周期状态
 */
enum CoroutineState: string
{
    /** 已创建，尚未开始执行 */
    case Pending = 'pending';

    /** 正在执行或已挂起等待唤醒 */
    case Running = 'running';

    /** 正常执行完毕 */
    case Completed = 'completed';

    /** 抛出异常而结束 */
    case Failed = 'failed';

    /** 被取消 */
    case Cancelled = 'cancelled';

    /**
     * 是否已进入终态
     */
    public function isFinished(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Cancelled => true,
            self::Pending, self::Running => false,
        };
    }
}
