<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 输出接口
 * 
 * 定义命令行输出的接口
 */
interface OutputInterface
{
    /**
     * 输出一行信息
     * 
     * @param string $message 信息内容
     * @param string $style 信息样式
     * @return void
     */
    public function writeln(string $message, string $style = 'info'): void;

    /**
     * 输出信息
     * 
     * @param string $message 信息内容
     * @param string $style 信息样式
     * @return void
     */
    public function write(string $message, string $style = 'info'): void;
}