<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 输出类
 * 
 * 处理命令行输出
 */
class Output implements OutputInterface
{
    /**
     * 输出一行信息
     * 
     * @param string $message 信息内容
     * @param string $style 信息样式
     * @return void
     */
    public function writeln(string $message, string $style = 'info'): void
    {
        $this->write($message . PHP_EOL, $style);
    }

    /**
     * 输出信息
     * 
     * @param string $message 信息内容
     * @param string $style 信息样式
     * @return void
     */
    public function write(string $message, string $style = 'info'): void
    {
        // 简单的颜色映射
        $styles = [
            'info' => "\033[36m",      // 青色
            'success' => "\033[32m",   // 绿色
            'warning' => "\033[33m",   // 黄色
            'error' => "\033[31m",     // 红色
            'bold' => "\033[1m",       // 粗体
        ];
        
        $reset = "\033[0m";
        
        if (isset($styles[$style])) {
            $message = $styles[$style] . $message . $reset;
        }
        
        echo $message;
    }
}