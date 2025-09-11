<?php

declare(strict_types=1);

namespace Nova\Fibers\Contracts;

/**
 * 可执行任务接口
 * 
 * 定义了可在纤程中执行的任务必须实现的方法
 */
interface Runnable
{
    /**
     * 执行任务
     * 
     * @return void
     */
    public function run(): void;

    /**
     * 获取任务执行结果
     * 
     * @return mixed 任务执行结果
     */
    public function getResult();
}
