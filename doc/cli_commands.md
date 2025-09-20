# CLI 命令详解

## 概述

nova/fibers 提供了一套完整的命令行工具，用于初始化配置、监控运行状态、执行性能测试等操作。这些命令可以帮助开发者更方便地使用和管理纤程应用。

## 基本命令

### 初始化配置 (init)

用于生成项目配置文件，支持交互式配置和框架特定配置。

```bash
# 交互式初始化配置
php vendor/bin/fibers init

# 指定框架初始化配置
php vendor/bin/fibers init --framework=laravel

# 使用默认配置初始化
php vendor/bin/fibers init --default
```

### 查看状态 (status)

显示当前纤程池和调度器的运行状态。

```bash
# 查看基本状态信息
php vendor/bin/fibers status

# 查看详细状态信息
php vendor/bin/fibers status --verbose

# 以 JSON 格式输出状态
php vendor/bin/fibers status --format=json
```

### 清理资源 (cleanup)

清理僵尸纤程和释放资源。

```bash
# 清理所有僵尸纤程
php vendor/bin/fibers cleanup

# 强制清理所有资源
php vendor/bin/fibers cleanup --force
```

### 性能测试 (benchmark)

执行性能基准测试，评估系统性能。

```bash
# 基本性能测试
php vendor/bin/fibers benchmark

# 指定并发数进行测试
php vendor/bin/fibers benchmark --concurrency=100

# 指定测试持续时间
php vendor/bin/fibers benchmark --duration=60

# 详细输出测试结果
php vendor/bin/fibers benchmark --verbose
```

### 帮助信息 (help)

显示命令帮助信息。

```bash
# 显示所有命令帮助
php vendor/bin/fibers help

# 显示特定命令帮助
php vendor/bin/fibers help init
```

## 配置选项

### 配置文件路径

所有 CLI 命令都会自动查找配置文件，查找顺序如下：

1. 命令行参数 `--config` 指定的路径
2. 当前目录下的 `fibers.php` 文件
3. `config/fibers.php` 文件
4. 包默认配置

```bash
# 指定配置文件路径
php vendor/bin/fibers status --config=/path/to/custom/fibers.php
```

### 环境变量

CLI 命令支持通过环境变量配置行为：

```bash
# 设置日志级别
FIBERS_LOG_LEVEL=debug php vendor/bin/fibers status

# 设置配置文件路径
FIBERS_CONFIG_PATH=/path/to/config.php php vendor/bin/fibers init
```

## 框架集成命令

### Laravel 集成

```bash
# 发布配置文件
php artisan vendor:publish --tag=fibers-config

# 查看纤程状态
php artisan fibers:status

# 清理纤程资源
php artisan fibers:cleanup
```

### Symfony 集成

```bash
# 初始化配置
bin/console fibers:install

# 查看状态
bin/console fibers:status

# 执行基准测试
bin/console fibers:benchmark
```

### Yii3 集成

```bash
# 初始化配置
php yii fibers/setup

# 查看状态
php yii fibers/status

# 清理资源
php yii fibers/cleanup
```

### ThinkPHP8 集成

```bash
# 生成配置文件
php think fibers:config

# 查看状态
php think fibers:status

# 性能测试
php think fibers:benchmark
```

## 高级用法

### 自定义命令

可以通过扩展 `Nova\Fibers\Commands\Command` 类来创建自定义命令：

```php
use Nova\Fibers\Commands\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CustomCommand extends Command
{
    protected static $defaultName = 'fibers:custom';
    
    protected function configure()
    {
        $this->setDescription('Custom fibers command')
             ->setHelp('This command provides custom functionality...');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // 实现命令逻辑
        $output->writeln('Custom command executed successfully!');
        return Command::SUCCESS;
    }
}
```

### 命令钩子

CLI 命令支持前置和后置钩子：

```php
use Nova\Fibers\Commands\Command;

class StatusCommand extends Command
{
    protected function beforeExecute()
    {
        // 命令执行前的逻辑
        $this->log('Starting status check...');
    }
    
    protected function afterExecute($result)
    {
        // 命令执行后的逻辑
        $this->log('Status check completed.');
    }
}
```

## 配置文件详解

### 基本配置结构

```php
<?php

return [
    // 纤程池配置
    'fiber_pool' => [
        'size' => 16,           // 池大小
        'timeout' => 30,        // 超时时间（秒）
        'max_retries' => 3,     // 最大重试次数
        'retry_delay' => 1000,  // 重试延迟（毫秒）
    ],
    
    // 调度器配置
    'scheduler' => [
        'type' => 'local',      // 调度器类型
        'options' => [
            // 调度器特定选项
        ]
    ],
    
    // 通道配置
    'channels' => [
        'default' => [
            'buffer_size' => 10
        ]
    ],
    
    // 日志配置
    'logging' => [
        'level' => 'info',
        'file' => 'php://stderr'
    ],
    
    // 监控配置
    'monitoring' => [
        'enabled' => true,
        'interval' => 60 // 监控间隔（秒）
    ]
];
```

### 环境特定配置

支持根据不同环境加载不同配置：

```php
<?php

return [
    'fiber_pool' => [
        'size' => env('FIBER_POOL_SIZE', 16),
        'timeout' => env('FIBER_TIMEOUT', 30),
    ],
    
    'scheduler' => [
        'type' => env('SCHEDULER_TYPE', 'local'),
    ],
    
    // 开发环境特定配置
    'development' => [
        'logging' => [
            'level' => 'debug',
        ]
    ],
    
    // 生产环境特定配置
    'production' => [
        'logging' => [
            'level' => 'error',
        ]
    ]
];
```

## 最佳实践

1. **定期执行清理命令**：定期执行 `cleanup` 命令以释放资源。
2. **监控系统状态**：使用 `status` 命令定期检查系统运行状态。
3. **性能基准测试**：在部署前使用 `benchmark` 命令评估系统性能。
4. **配置版本控制**：将配置文件纳入版本控制，确保环境一致性。
5. **日志记录**：启用适当的日志级别以帮助故障排除。

## 故障排除

### 命令未找到

检查是否正确安装了 nova/fibers 包，以及 `vendor/bin/fibers` 文件是否存在。

### 权限问题

确保执行命令的用户具有足够的权限访问配置文件和相关资源。

### 配置文件错误

检查配置文件语法是否正确，以及配置项是否符合要求。

## 参考资料

- [Symfony Console Component](https://symfony.com/doc/current/components/console.html)
- [Laravel Artisan Commands](https://laravel.com/docs/artisan)
- [PHP CLI Usage](https://www.php.net/manual/en/features.commandline.php)