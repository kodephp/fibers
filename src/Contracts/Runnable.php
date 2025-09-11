<?php

declare(strict_types=1);

namespace Nova\Fibers\Contracts;

/**
 * 可运行接口
 *
 * @package Nova\Fibers\Contracts
 */
interface Runnable
{
    /**
     * 执行任务
     *
     * @return mixed
     */
    public function run(): mixed;
}
