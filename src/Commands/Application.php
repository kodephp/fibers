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
     * @param \Nova\Fibers\Commands\Command $command 命令实例
     * @return void
     */
    public function add(\Nova\Fibers\Commands\Command $command): void
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
     * @return int 退出码
     */
    public function run(Input $input = null, Output $output = null): int
    {
        if ($input === null) {
            $input = new Input();
        }

        if ($output === null) {
            $output = new Output();
        }

        $argv = $input->getRawArguments();

        // 检查是否请求帮助
        if (in_array('-h', $argv) || in_array('--help', $argv)) {
            $this->displayHelp($output);
            return 0;
        }

        // 检查是否请求版本信息
        if (in_array('-V', $argv) || in_array('--version', $argv)) {
            $output->writeln("{$this->name} version {$this->version}", 'info');
            return 0;
        }

        // 获取命令名称
        $commandName = $argv[0] ?? 'list';

        // 查找命令
        $command = $this->get($commandName);

        if ($command === null) {
            $output->writeln("Command '{$commandName}' not found.", 'error');
            $this->listCommands($output);
            return 1;
        }

        // 设置命令名称
        $command->setName($commandName);

        // 执行命令
        return $command->handle($input->getArguments());
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
