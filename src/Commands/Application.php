<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * CLI 应用程序
 *
 * @package Nova\Fibers\Commands
 */
class Application
{
    /**
     * 已注册的命令
     *
     * @var Command[]
     */
    protected array $commands = [];

    /**
     * 应用名称
     *
     * @var string
     */
    protected string $name = 'Nova Fibers CLI';

    /**
     * 应用版本
     *
     * @var string
     */
    protected string $version = '1.0.0';

    /**
     * Application 构造函数
     */
    public function __construct()
    {
        $this->registerDefaultCommands();
    }

    /**
     * 设置应用名称
     *
     * @param string $name 应用名称
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 设置应用版本
     *
     * @param string $version 应用版本
     * @return self
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * 注册命令
     *
     * @param Command|object $command 命令实例
     * @return self
     */
    public function add($command): self
    {
        // 检查是否是Symfony命令
        if (is_object($command) && get_class($command) !== 'Nova\Fibers\Commands\Command' && 
            is_subclass_of($command, 'Symfony\Component\Console\Command\Command')) {
            // 为Symfony命令创建适配器
            $adaptedCommand = new class($command) extends Command {
                private $symfonyCommand;
                
                public function __construct($symfonyCommand) {
                    $this->symfonyCommand = $symfonyCommand;
                    
                    // 获取命令名称和描述
                    $reflection = new \ReflectionClass($symfonyCommand);
                    
                    // 获取静态属性
                    $defaultName = $reflection->getStaticPropertyValue('defaultName', null);
                    $defaultDescription = $reflection->getStaticPropertyValue('defaultDescription', null);
                    
                    // 如果静态属性不存在，尝试调用方法
                    if ($defaultName === null) {
                        $defaultName = $symfonyCommand->getName();
                    }
                    
                    if ($defaultDescription === null) {
                        $defaultDescription = $symfonyCommand->getDescription();
                    }
                    
                    $this->name = $defaultName ?? 'unnamed-command';
                    $this->description = $defaultDescription ?? 'No description';
                }
                
                public function handle(array $input = []): int {
                    // 创建Symfony应用来运行命令
                    $application = new \Symfony\Component\Console\Application();
                    $application->add($this->symfonyCommand);
                    
                    // 创建输入对象
                    $inputArray = array_merge([$_SERVER['argv'][0] ?? 'fibers', $this->name], $input);
                    $symfonyInput = new \Symfony\Component\Console\Input\ArgvInput($inputArray);
                    
                    // 创建输出对象
                    $symfonyOutput = new \Symfony\Component\Console\Output\ConsoleOutput();
                    
                    // 运行命令
                    return $application->run($symfonyInput, $symfonyOutput);
                }
            };
            
            $this->commands[$adaptedCommand->getName()] = $adaptedCommand;
        } else {
            $this->commands[$command->getName()] = $command;
        }
        
        return $this;
    }

    /**
     * 注册默认命令
     *
     * @return void
     */
    protected function registerDefaultCommands(): void
    {
        $this->add(new InitCommand());
        $this->add(new StatusCommand());
        $this->add(new RunExampleCommand());
        // 可以在这里注册更多默认命令
    }

    /**
     * 运行应用
     *
     * @param array|null $argv 命令行参数
     * @return int 退出码
     */
    public function run(?array $argv = null): int
    {
        if ($argv === null) {
            global $argv;
        }

        // 显示应用信息
        echo "{$this->name} version {$this->version}\n\n";

        // 检查是否有命令参数
        if (count($argv) < 2) {
            $this->showHelp();
            return 1;
        }

        $commandName = $argv[1];

        // 检查是否是帮助命令
        if ($commandName === '--help' || $commandName === '-h') {
            $this->showHelp();
            return 0;
        }

        // 查找命令
        if (!isset($this->commands[$commandName])) {
            echo "Command '{$commandName}' not found.\n\n";
            $this->showHelp();
            return 1;
        }

        $command = $this->commands[$commandName];

        // 检查是否需要显示命令帮助
        if (isset($argv[2]) && ($argv[2] === '--help' || $argv[2] === '-h')) {
            $command->showHelp();
            return 0;
        }

        // 执行命令
        $input = array_slice($argv, 2);
        return $command->handle($input);
    }

    /**
     * 显示帮助信息
     *
     * @return void
     */
    protected function showHelp(): void
    {
        echo "Usage: php fibers [command] [options]\n\n";
        echo "Available commands:\n";

        foreach ($this->commands as $command) {
            printf("  %-15s %s\n", $command->getName(), $command->getDescription());
        }

        echo "\nUse 'php fibers [command] --help' for more information about a command.\n";
    }
}
