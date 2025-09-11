<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 输入接口
 * 
 * 定义命令行输入的接口
 */
interface InputInterface
{
    /**
     * 获取参数值
     * 
     * @param string $name 参数名称
     * @return mixed 参数值
     */
    public function getArgument(string $name): mixed;

    /**
     * 获取选项值
     * 
     * @param string $name 选项名称
     * @return mixed 选项值
     */
    public function getOption(string $name): mixed;

    /**
     * 检查是否设置了选项
     * 
     * @param string $name 选项名称
     * @return bool 是否设置了选项
     */
    public function hasOption(string $name): bool;

    /**
     * 获取所有参数
     * 
     * @return array 所有参数
     */
    public function getArguments(): array;

    /**
     * 获取所有选项
     * 
     * @return array 所有选项
     */
    public function getOptions(): array;
}