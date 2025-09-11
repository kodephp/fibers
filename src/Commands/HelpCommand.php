<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 显示帮助信息的命令类
 */
class HelpCommand extends Command
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct('help', 'Display help for a command');
    }

    /**
     * 配置命令
     * 
     * @return void
     */
    public function configure(): void
    {
        $this->addArgument('command_name', InputArgument::OPTIONAL, 'The command name', 'list');
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
        $commandName = $input->getArgument('command_name');
        
        // 获取应用中的命令
        global $app;
        if (isset($app) && $app instanceof Application) {
            if ($commandName === 'list') {
                $output->writeln("Usage: command [options] [arguments]", 'info');
                $output->writeln("");
                $output->writeln("Options:", 'info');
                $output->writeln("  -h, --help            Display this help message");
                $output->writeln("  -V, --version         Display this application version");
                $output->writeln("");
                $output->writeln("Available commands:", 'info');
                
                $commands = $app->all();
                foreach ($commands as $command) {
                    $output->writeln(sprintf("  %-20s %s", $command->getName(), $command->getDescription()), 'info');
                }
                
                return 0;
            }
            
            $command = $app->get($commandName);
            if ($command) {
                $output->writeln("Usage: {$commandName} [options] [arguments]", 'info');
                $output->writeln("");
                $output->writeln("Description:", 'info');
                $output->writeln("  {$command->getDescription()}", 'info');
                $output->writeln("");
                
                $arguments = $command->getArguments();
                if (!empty($arguments)) {
                    $output->writeln("Arguments:", 'info');
                    foreach ($arguments as $argument) {
                        $output->writeln(sprintf(
                            "  %-20s %s",
                            $argument['name'],
                            $argument['description']
                        ), 'info');
                    }
                    $output->writeln("");
                }
                
                $options = $command->getOptions();
                if (!empty($options)) {
                    $output->writeln("Options:", 'info');
                    foreach ($options as $option) {
                        $shortcut = $option['shortcut'] ? "-{$option['shortcut']}, " : "";
                        $output->writeln(sprintf(
                            "  %s--%-17s %s",
                            $shortcut,
                            $option['name'],
                            $option['description']
                        ), 'info');
                    }
                }
                
                return 0;
            }
        }
        
        $output->writeln("Command '{$commandName}' not found.", 'error');
        return 1;
    }
}