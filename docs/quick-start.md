# Kode/Fibers 快速开始指南

## 安装

```bash
composer require kode/fibers
```

## 初始化配置

### 基本用法

```bash
php vendor/bin/fibers init
```

这将创建一个基本的配置文件，自动检测您的环境并设置推荐的纤程池大小。

### 指定框架

```bash
# Laravel
php vendor/bin/fibers init laravel

# Symfony
php vendor/bin/fibers init symfony

# Yii3
php vendor/bin/fibers init yii3

# ThinkPHP
php vendor/bin/fibers init thinkphp

# 默认/原生PHP
php vendor/bin/fibers init default
```

### 强制覆盖现有配置

```bash
php vendor/bin/fibers init --force
```

## 配置文件说明

初始化命令会在项目根目录的 `config/` 文件夹中创建 `fibers.php` 配置文件。

### 默认池配置

```php
'default_pool' => [
    'size' => env('FIBER_POOL_SIZE', CpuInfo::get() * 4),
    'max_exec_time' => 30,
    'gc_interval' => 100,
    'context' => [],
],
```

- `size`: 纤程池大小，默认为 CPU 核心数的 4 倍
- `max_exec_time`: 单个纤程最大执行时间（秒）
- `gc_interval`: 每执行多少次任务后触发垃圾回收
- `context`: 默认上下文变量

### 通道配置

```php
'channels' => [
    // 'example' => ['buffer_size' => 100],
],
```

定义纤程间通信的通道及其缓冲区大小。

### 功能配置

```php
'features' => [
    'auto_suspend_io' => true,
    'enable_monitoring' => true,
    'strict_destruct_check' => version_compare(PHP_VERSION, '8.4.0', '<'),
],
```

- `auto_suspend_io`: 自动挂起 I/O 操作
- `enable_monitoring`: 启用监控功能
- `strict_destruct_check`: 在 PHP < 8.4 版本中启用严格的析构函数检查

### 框架集成

```php
'framework' => [
    'name' => env('APP_FRAMEWORK', 'default'),
    'service_provider' => true,
    'provider_class' => env('FIBER_PROVIDER_CLASS', 'Kode\Fibers\Providers\GenericProvider'),
],
```

框架特定的配置选项。

### 环境配置

```php
'environment' => [
    'php_version' => PHP_VERSION,
    'cpu_cores' => CpuInfo::get(),
    'detected_framework' => 'Default/Plain PHP',
],
```

运行环境的相关信息。

## Laravel 特定配置

当使用 Laravel 框架时，配置文件还会包含以下特定选项：

```php
'laravel' => [
    'middleware' => [
        'enable_fibers' => true,
        'timeout_middleware' => true,
    ],
    'facades' => [
        'Fibers' => 'Kode\Fibers\Facades\Fibers',
    ],
    'commands' => [
        'Kode\Fibers\Commands\FibersCommand',
    ],
],
```

## Symfony 特定配置

当使用 Symfony 框架时，还会创建 `config/packages/fibers.yaml` 文件：

```yaml
kode_fibers:
    default_pool:
        size: '%env(int:FIBER_POOL_SIZE)%'
        max_exec_time: 30
        gc_interval: 100
    
    channels: []
    
    features:
        auto_suspend_io: true
        enable_monitoring: true
        strict_destruct_check: true
    
    framework:
        name: 'symfony'
        service_provider: true
```

## 环境检查

初始化过程中会自动检查运行环境，并报告可能影响纤程执行的问题：

```
Environment warnings:
  ⚠️  function_disabled: proc_open is disabled
  ⚠️  fiber_unsafe: set_time_limit may break fiber suspension
```

## 使用示例

### 基本纤程执行

```php
use Kode\Fibers\Facades\Fiber;

// 启动一个纤程并等待结果
$result = Fiber::run(fn() => sleep(1) || 'Hello from Fiber!');

echo $result; // 输出: Hello from Fiber!
```

### 使用纤程池

```php
use Kode\Fibers\FiberPool;

$pool = new FiberPool([
    'size' => 64,
    'max_exec_time' => 30,
    'gc_interval' => 100
]);

$results = $pool->concurrent([
    fn() => file_get_contents('http://api.a.com'),
    fn() => file_get_contents('http://api.b.com'),
    fn() => \RedisClient::get('key')
]);

print_r($results);
```

### 通道通信

```php
use Kode\Fibers\Channel\Channel;

$ch = Channel::make('download-results', 10);

// 生产者
Fiber::run(function () use ($ch) {
    foreach ([1, 2, 3] as $i) {
        $ch->push("Data $i");
        usleep(100000);
    }
    $ch->close();
});

// 消费者
while ($msg = $ch->pop(1)) { // 超时1秒
    echo $msg . "\n";
}
```

## CLI 命令

### 查看帮助

```bash
php vendor/bin/fibers help
```

### 初始化配置

```bash
php vendor/bin/fibers init [framework] [--force]
```

### 查看状态

```bash
php vendor/bin/fibers status
```

### 清理僵尸纤程

```bash
php vendor/bin/fibers cleanup
```

### 性能压测

```bash
php vendor/bin/fibers benchmark --concurrency=1000
```