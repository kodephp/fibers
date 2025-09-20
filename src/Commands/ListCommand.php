<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 列出所有命令的命令类
 */
class ListCommand extends Command
{
    /**
     * ListCommand 构造函数
     */
    public function __construct()
    {
        $this->setName('list')
             ->setDescription('List all available commands');
    }

    /**
     * 执行命令
     * 
     * @param array $input 输入参数
     * @return int 退出码
     */
    public function handle(array $input = []): int
    {
        global $app;
        $output = new Output();
        
        $output->writeln("Available commands:", 'info');
        
        if (isset($app) && $app instanceof Application) {
            $commands = $app->all();
            foreach ($commands as $command) {
                $output->writeln(sprintf("  %-20s %s", $command->getName(), $command->getDescription()), 'info');
            }
        } else {
            $output->writeln("  No commands available.", 'error');
        }
        
        return 0;
    }
}