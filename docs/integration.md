# 框架集成指南

## 自动检测框架

`FrameworkDetector` 自动检测当前运行环境：

```php
use Kode\Fibers\Integration\FrameworkDetector;

// 检测当前框架
$framework = FrameworkDetector::detect();

// 检测是否 Laravel
if (FrameworkDetector::isLaravel()) {
    // Laravel 环境
}

// 获取运行时信息
$info = FrameworkDetector::getRuntimeInfo();
// ['framework' => 'laravel', 'swoole' => true, 'fiber' => true, ...]
```

## 支持的框架

| 框架 | 检测方法 | 集成类 |
|------|---------|--------|
| Laravel | `isLaravel()` | `LaravelServiceProvider` |
| Symfony | `isSymfony()` | `SymfonyBundle` |
| Yii3 | `isYii3()` | `Yii3ServiceProvider` |
| ThinkPHP | `isThinkPHP()` | `ThinkPHPService` |
| Hyperf | `isHyperf()` | `HyperfServiceProvider` |
| Webman | `isWebman()` | `WebmanBootstrap` |
| Lumen | `isLumen()` | `LumenServiceProvider` |

## 自动初始化

```php
use Kode\Fibers\Integration\IntegrationManager;

// 自动检测并初始化
IntegrationManager::boot();

// 指定框架初始化
IntegrationManager::boot('laravel');

// 检查服务是否已集成
if (IntegrationManager::hasIntegration('fibers')) {
    // Fibers 已集成
}
```

## Laravel 集成

### 安装服务提供者

在 `config/app.php` 中添加：

```php
'providers' => [
    // ...
    Kode\Fibers\Integration\Providers\LaravelServiceProvider::class,
],
```

### 使用 Facade

```php
use Kode\Fibers\Facades\Fibers;

// 执行异步任务
$result = Fibers::go(function () {
    return 'Hello Fiber';
});

// 批量处理
$results = Fibers::batch($items, function ($item) {
    return process($item);
}, 10);
```

## Symfony 集成

### 注册 Bundle

在 `config/bundles.php` 中添加：

```php
return [
    // ...
    Kode\Fibers\Integration\Providers\SymfonyBundle::class => ['all' => true],
];
```

## Hyperf 集成

```php
// config/autoload.php
return [
    'dependencies' => [
        \Kode\Fibers\Fibers::class => \Kode\Fibers\Fibers::class,
    ],
];
```

## ThinkPHP 集成

```php
// application/tags.php
return [
    'app_init' => [
        \Kode\Fibers\Integration\Providers\ThinkPHPService::class,
    ],
];
```

## 运行时检测

```php
use Kode\Fibers\Integration\FrameworkDetector;

$runtime = FrameworkDetector::getRuntimeInfo();

echo "框架: {$runtime['framework']}\n";
echo "Swoole: " . ($runtime['swoole'] ? '是' : '否') . "\n";
echo "Swow: " . ($runtime['swow'] ? '是' : '否') . "\n";
echo "Fiber: " . ($runtime['fiber'] ? '是' : '否') . "\n";
echo "PHP版本: {$runtime['php_version']}\n";
```
