<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

use Nova\Fibers\Commands\Command;
use Nova\Fibers\Commands\Input;
use Nova\Fibers\Commands\Output;

/**
 * 命令行应用类
 * 
 * 管理和执行命令
 */
class Application
{
    /**
     * 命令数组
     * 
     * @var Command[]
     */
    private array $commands = [];

    /**
     * 应用名称
     * 
     * @var string
     */
    private string $name;

    /**
     * 应用版本
     * 
     * @var string
     */
    private string $version;

    /**
     * 构造函数
     * 
     * @param string $name 应用名称
     * @param string $version 应用版本
     */
    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN')
    {
        $this->name = $name;
        $this->version = $version;
    }

    /**
     * 添加命令
     * 
     * @param Command $command 命令实例
     * @return void
     */
    public function add(Command $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    /**
     * 获取命令
     * 
     * @param string $name 命令名称
     * @return Command|null 命令实例
     */
    public function get(string $name): ?Command
    {
        return $this->commands[$name] ?? null;
    }

    /**
     * 获取所有命令
     * 
     * @return Command[] 命令数组
     */
    public function all(): array
    {
        return $this->commands;
    }

    /**
     * 运行应用
     * 
     * @param Input|null $input 输入实例
     * @param Output|null $output 输出实例
     * @return int 命令执行结果
     */
    public function run(Input $input = null, Output $output = null): int
    {
        if ($input === null) {
            $input = new Input();
        }
        
        if ($output === null) {
            $output = new Output();
        }
        
        // 获取命令名称
        $rawArguments = $input->getRawArguments();
        $commandName = $rawArguments[0] ?? 'list';
        
        // 处理帮助选项
        if ($input->hasOption('help') || $input->hasOption('h')) {
            $this->displayHelp($output);
            return 0;
        }
        
        // 处理版本选项
        if ($input->hasOption('version') || $input->hasOption('V')) {
            $output->writeln("{$this->name} version {$this->version}", 'info');
            return 0;
        }
        
        // 显示命令列表
        if ($commandName === 'list' || $commandName === 'help') {
            $this->listCommands($output);
            return 0;
        }
        
        // 查找并执行命令
        if (!isset($this->commands[$commandName])) {
            $output->writeln("Command '{$commandName}' is not defined.", 'error');
            return 1;
        }
        
        $command = $this->commands[$commandName];
        $command->setOutput($output);
        
        // 配置命令
        $command->configure();
        
        // 执行命令
        return $command->execute($input, $output);
    }

    /**
     * 显示帮助信息
     * 
     * @param Output $output 输出实例
     * @return void
     */
    private function displayHelp(Output $output): void
    {
        $output->writeln("Usage: command [options] [arguments]", 'info');
        $output->writeln("");
        $output->writeln("Options:", 'info');
        $output->writeln("  -h, --help            Display this help message");
        $output->writeln("  -V, --version         Display this application version");
        $output->writeln("");
        $output->writeln("Available commands:", 'info');
        $this->listCommands($output);
    }

    /**
     * 列出所有命令
     * 
     * @param Output $output 输出实例
     * @return void
     */
    private function listCommands(Output $output): void
    {
        $output->writeln("Available commands:", 'info');
        foreach ($this->commands as $command) {
            $output->writeln(sprintf("  %-20s %s", $command->getName(), $command->getDescription()), 'info');
        }
    }
}
