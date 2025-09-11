<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 输入类
 * 
 * 处理命令行输入参数和选项
 */
class Input implements InputInterface
{
    /**
     * 参数数组
     * 
     * @var array
     */
    private array $arguments = [];

    /**
     * 选项数组
     * 
     * @var array
     */
    private array $options = [];

    /**
     * 原始参数数组
     * 
     * @var array
     */
    private array $rawArguments = [];

    /**
     * 构造函数
     * 
     * @param array $argv 命令行参数数组
     */
    public function __construct(array $argv = null)
    {
        if ($argv === null) {
            $argv = $_SERVER['argv'] ?? [];
        }
        
        // 移除脚本名称
        array_shift($argv);
        
        $this->rawArguments = $argv;
        $this->parseArguments($argv);
    }

    /**
     * 解析参数
     * 
     * @param array $argv 参数数组
     * @return void
     */
    private function parseArguments(array $argv): void
    {
        $arguments = [];
        $options = [];
        
        $i = 0;
        while ($i < count($argv)) {
            $arg = $argv[$i];
            
            // 处理选项
            if (str_starts_with($arg, '--')) {
                $option = substr($arg, 2);
                $value = null;
                
                // 检查是否有值
                if (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-')) {
                    $value = $argv[$i + 1];
                    $i += 2;
                } else {
                    $value = true;
                    $i++;
                }
                
                $options[$option] = $value;
            } elseif (str_starts_with($arg, '-')) {
                // 处理短选项
                $option = substr($arg, 1);
                $value = null;
                
                // 检查是否有值
                if (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-')) {
                    $value = $argv[$i + 1];
                    $i += 2;
                } else {
                    $value = true;
                    $i++;
                }
                
                $options[$option] = $value;
            } else {
                // 处理参数
                $arguments[] = $arg;
                $i++;
            }
        }
        
        $this->arguments = $arguments;
        $this->options = $options;
    }

    /**
     * 获取参数值
     * 
     * @param string $name 参数名称
     * @return mixed 参数值
     */
    public function getArgument(string $name): mixed
    {
        // 按名称查找参数（需要在命令中定义参数名称与位置的映射）
        // 这里简化处理，直接按位置查找
        $index = array_search($name, array_keys($this->arguments));
        if ($index !== false && isset($this->arguments[$index])) {
            return $this->arguments[$index];
        }
        
        return null;
    }

    /**
     * 获取选项值
     * 
     * @param string $name 选项名称
     * @return mixed 选项值
     */
    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /**
     * 检查是否设置了选项
     * 
     * @param string $name 选项名称
     * @return bool 是否设置了选项
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * 获取所有参数
     * 
     * @return array 所有参数
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * 获取所有选项
     * 
     * @return array 所有选项
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * 获取原始参数数组
     * 
     * @return array 原始参数数组
     */
    public function getRawArguments(): array
    {
        return $this->rawArguments;
    }
}