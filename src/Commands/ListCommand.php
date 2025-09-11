<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 列出所有命令的命令类
 */
class ListCommand extends Command
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct('list', 'List all available commands');
    }

    /**
     * 配置命令
     * 
     * @return void
     */
    public function configure(): void
    {
        // 这个命令不需要额外的参数或选项
    }

    /**
     * 执行命令
     * 
     * @param InputInterface $input 输入接口
     * @param OutputInterface $output 输出接口
     * @return int 命令执行结果
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Available commands:", 'info');
        
        // 获取应用中的所有命令
        global $app;
        if (isset($app) && $app instanceof Application) {
            $commands = $app->all();
            foreach ($commands as $command) {
                $output->writeln(sprintf("  %-20s %s", $command->getName(), $command->getDescription()), 'info');
            }
        } else {
            $output->writeln("  No commands available.", 'warning');
        }
        
        return 0;
    }
}