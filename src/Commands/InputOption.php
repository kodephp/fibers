<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 输入选项类
 * 
 * 定义命令行选项的模式
 */
class InputOption
{
    /**
     * 无值选项
     */
    public const VALUE_NONE = 1;

    /**
     * 必需值选项
     */
    public const VALUE_REQUIRED = 2;

    /**
     * 可选值选项
     */
    public const VALUE_OPTIONAL = 4;

    /**
     * 数组值选项
     */
    public const VALUE_IS_ARRAY = 8;
}