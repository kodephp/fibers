<?php

namespace Nova\Fibers\Console;

use Nova\Fibers\Commands\InitCommand;
use Nova\Fibers\Commands\StatusCommand;
use Nova\Fibers\Commands\CleanupCommand;
use Nova\Fibers\Commands\BenchmarkCommand;

/**
 * Application - 控制台应用入口
 * 
 * 用于注册和运行所有Nova Fibers相关的控制台命令
 */
class Application extends \Symfony\Component\Console\Application
{
    /**
     * 应用名称
     */
    public const NAME = 'Nova Fibers Console';

    /**
     * 应用版本
     */
    public const VERSION = '1.0.0';

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);
        
        // 注册所有命令
        $this->addCommands([
            new InitCommand(),
            new StatusCommand(),
            new CleanupCommand(),
            new BenchmarkCommand(),
        ]);
    }
}