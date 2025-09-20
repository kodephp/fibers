<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

use Nova\Fibers\Commands\Input;
use Nova\Fibers\Commands\Output;
use Nova\Fibers\Support\ConfigLocator;

/**
 * 命令基类
 * 
 * 提供命令行工具的基础功能，所有命令都应该继承此类
 */
abstract class Command
{
    /**
     * 命令名称
     * 
     * @var string
     */
    protected string $name;

    /**
     * 命令描述
     * 
     * @var string
     */
    protected string $description = '';

    /**
     * 命令帮助信息
     * 
     * @var string
     */
    protected string $help = '';

    /**
     * 命令定义的选项
     * 
     * @var array
     */
    protected array $options = [];

    /**
     * 命令定义的参数
     * 
     * @var array
     */
    protected array $arguments = [];

    /**
     * 输入对象
     * 
     * @var Input
     */
    protected Input $input;

    /**
     * 输出对象
     * 
     * @var Output
     */
    protected Output $output;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->configure();
        
        // 确保命令名称已设置
        if (!isset($this->name)) {
            throw new \InvalidArgumentException('Command name must be set');
        }
    }

    /**
     * 配置命令
     * 
     * 子类应该重写此方法来配置命令名称、描述、选项和参数
     * 
     * @return void
     */
    abstract protected function configure(): void;

    /**
     * 执行命令
     * 
     * 子类应该重写此方法来实现命令的具体逻辑
     * 
     * @param Input $input 输入对象
     * @param Output $output 输出对象
     * @return int 命令退出码
     */
    abstract public function handle(Input $input, Output $output): int;

    /**
     * 运行命令
     * 
     * @param array $args 命令行参数
     * @return int 命令退出码
     */
    public function run(array $args = []): int
    {
        // 解析命令行参数
        $this->input = new Input($args, $this->arguments, $this->options);
        $this->output = new Output();

        try {
            // 验证输入
            if (!$this->validateInput()) {
                return 1;
            }

            // 执行命令
            $exitCode = $this->handle($this->input, $this->output);
            
            // 确保退出码是整数
            if (!is_int($exitCode)) {
                $exitCode = 0;
            }
            
            return $exitCode;
        } catch (\Throwable $e) {
            // 显示错误信息
            $this->output->writeln('Error: ' . $e->getMessage(), 'error');
            
            // 在调试模式下显示详细错误
            if ($this->input->hasOption('debug') && $this->input->getOption('debug')) {
                $this->output->writeln('Stack trace:', 'error');
                $this->output->writeln($e->getTraceAsString(), 'error');
            }
            
            return 1;
        }
    }

    /**
     * 验证输入
     * 
     * @return bool 输入是否有效
     */
    protected function validateInput(): bool
    {
        // 检查必需参数
        foreach ($this->arguments as $name => $config) {
            if (isset($config['required']) && $config['required'] && !$this->input->hasArgument($name)) {
                $this->output->writeln(sprintf('Error: Missing required argument "%s"', $name), 'error');
                $this->showHelp();
                return false;
            }
        }

        // 检查选项值范围
        foreach ($this->options as $name => $config) {
            if (isset($config['choices']) && $this->input->hasOption($name)) {
                $value = $this->input->getOption($name);
                if (!in_array($value, $config['choices'])) {
                    $this->output->writeln(sprintf(
                        'Error: Invalid value "%s" for option "%s". Expected one of: %s',
                        $value, $name, implode(', ', $config['choices'])
                    ), 'error');
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 添加命令选项
     * 
     * @param string $name 选项名称
     * @param string|array|null $shortcut 选项快捷键
     * @param int $mode 选项模式
     * @param string $description 选项描述
     * @param mixed $default 默认值
     * @param array $choices 可选值列表
     * @return $this
     */
    protected function addOption(string $name, $shortcut = null, int $mode = 0, string $description = '', $default = null, array $choices = [])
    {
        $this->options[$name] = [
            'shortcut' => $shortcut,
            'mode' => $mode,
            'description' => $description,
            'default' => $default,
            'choices' => $choices
        ];
        
        return $this;
    }

    /**
     * 添加命令参数
     * 
     * @param string $name 参数名称
     * @param bool $required 是否必需
     * @param string $description 参数描述
     * @param mixed $default 默认值
     * @return $this
     */
    protected function addArgument(string $name, bool $required = false, string $description = '', $default = null)
    {
        $this->arguments[$name] = [
            'required' => $required,
            'description' => $description,
            'default' => $default
        ];
        
        return $this;
    }

    /**
     * 显示命令帮助信息
     * 
     * @return void
     */
    protected function showHelp(): void
    {
        $this->output->writeln('Usage:', 'info');
        $this->output->writeln(sprintf('  %s [options]', $this->name), 'info');
        
        // 显示参数
        if (!empty($this->arguments)) {
            $this->output->writeln('');
            $this->output->writeln('Arguments:', 'info');
            foreach ($this->arguments as $name => $config) {
                $required = $config['required'] ? '<required>' : '[optional]';
                $default = isset($config['default']) ? ' (default: ' . var_export($config['default'], true) . ')' : '';
                $this->output->writeln(sprintf(
                    '  %s%s - %s%s',
                    $name,
                    $required,
                    $config['description'],
                    $default
                ));
            }
        }
        
        // 显示选项
        if (!empty($this->options)) {
            $this->output->writeln('');
            $this->output->writeln('Options:', 'info');
            foreach ($this->options as $name => $config) {
                $shortcuts = [];
                if ($config['shortcut']) {
                    if (is_array($config['shortcut'])) {
                        $shortcuts = array_map(fn($s) => '-' . $s, $config['shortcut']);
                    } else {
                        $shortcuts = ['-' . $config['shortcut']];
                    }
                }
                
                $longOption = '--' . $name;
                $options = array_merge($shortcuts, [$longOption]);
                $optionText = implode(', ', $options);
                
                $default = isset($config['default']) ? ' (default: ' . var_export($config['default'], true) . ')' : '';
                $choices = !empty($config['choices']) ? ' (choices: ' . implode(', ', $config['choices']) . ')' : '';
                
                $this->output->writeln(sprintf(
                    '  %s - %s%s%s',
                    $optionText,
                    $config['description'],
                    $default,
                    $choices
                ));
            }
        }
        
        // 显示详细帮助
        if (!empty($this->help)) {
            $this->output->writeln('');
            $this->output->writeln('Help:', 'info');
            $this->output->writeln('  ' . $this->help);
        }
    }

    /**
     * 获取命令名称
     * 
     * @return string 命令名称
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取命令描述
     * 
     * @return string 命令描述
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * 获取命令帮助信息
     * 
     * @return string 命令帮助信息
     */
    public function getHelp(): string
    {
        return $this->help;
    }

    /**
     * 获取命令选项
     * 
     * @return array 命令选项
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * 获取命令参数
     * 
     * @return array 命令参数
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
