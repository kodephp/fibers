<?php

declare(strict_types=1);

namespace Kode\Fibers\Contracts;

/**
 * 可运行接口
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