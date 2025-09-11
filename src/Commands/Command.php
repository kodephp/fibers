<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * CLI 命令基类
 *
 * @package Nova\Fibers\Commands
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
    protected string $description;

    /**
     * 命令参数
     *
     * @var array
     */
    protected array $arguments = [];

    /**
     * 命令选项
     *
     * @var array
     */
    protected array $options = [];

    /**
     * 执行命令
     *
     * @param array $input 输入参数
     * @return int 退出码
     */
    abstract public function handle(array $input = []): int;

    /**
     * 获取命令名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 设置命令名称
     *
     * @param string $name 命令名称
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 获取命令描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * 设置命令描述
     *
     * @param string $description 命令描述
     * @return self
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * 添加参数
     *
     * @param string $name 参数名称
     * @param string $description 参数描述
     * @param bool $required 是否必需
     * @return self
     */
    public function addArgument(string $name, string $description, bool $required = true): self
    {
        $this->arguments[$name] = [
            'description' => $description,
            'required' => $required
        ];

        return $this;
    }

    /**
     * 添加选项
     *
     * @param string $name 选项名称
     * @param string $description 选项描述
     * @param mixed $default 默认值
     * @return self
     */
    public function addOption(string $name, string $description, $default = null): self
    {
        $this->options[$name] = [
            'description' => $description,
            'default' => $default
        ];

        return $this;
    }

    /**
     * 显示帮助信息
     *
     * @return void
     */
    public function showHelp(): void
    {
        echo "Usage: {$this->name}\n";
        echo "Description: {$this->description}\n\n";

        if (!empty($this->arguments)) {
            echo "Arguments:\n";
            foreach ($this->arguments as $name => $arg) {
                $required = $arg['required'] ? ' (required)' : ' (optional)';
                echo "  {$name}: {$arg['description']}{$required}\n";
            }
            echo "\n";
        }

        if (!empty($this->options)) {
            echo "Options:\n";
            foreach ($this->options as $name => $opt) {
                $default = $opt['default'] !== null ? " (default: {$opt['default']})" : '';
                echo "  --{$name}: {$opt['description']}{$default}\n";
            }
            echo "\n";
        }
    }

    /**
     * 检查是否为 Laravel 环境
     *
     * @return bool
     */
    protected function isLaravel(): bool
    {
        return class_exists(\Illuminate\Foundation\Application::class);
    }

    /**
     * 检查是否为 Symfony 环境
     *
     * @return bool
     */
    protected function isSymfony(): bool
    {
        return class_exists(\Symfony\Component\Console\Application::class);
    }

    /**
     * 检查是否为 Yii 环境
     *
     * @return bool
     */
    protected function isYii(): bool
    {
        return class_exists(\Yii::class);
    }

    /**
     * 检查是否为 ThinkPHP 环境
     *
     * @return bool
     */
    protected function isThinkPHP(): bool
    {
        return class_exists(\think\App::class);
    }
}
