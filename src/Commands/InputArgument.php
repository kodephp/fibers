<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 输入参数类
 * 
 * 定义命令行参数的模式
 */
class InputArgument
{
    /**
     * 必需参数
     */
    public const REQUIRED = 1;

    /**
     * 可选参数
     */
    public const OPTIONAL = 2;

    /**
     * 数组参数
     */
    public const IS_ARRAY = 4;
}