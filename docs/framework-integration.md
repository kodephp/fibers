# 框架集成

本指南将详细介绍如何在各种PHP框架中集成和使用Kode/Fibers库。

## Laravel 集成

Kode/Fibers 提供了完整的 Laravel 集成支持，包括服务提供者、门面和配置文件。

### 安装步骤

1. 安装 Kode/Fibers

```bash
composer require kode/fibers
```

2. 发布配置文件

```bash
php artisan vendor:publish --tag=fibers-config
```

这将在 `config` 目录下创建 `fibers.php` 配置文件。

3. （可选）在 `config/app.php` 中注册服务提供者和门面

```php
'providers' => [
    // ...
    Kode\Fibers\Providers\LaravelServiceProvider::class,
],

'aliases' => [
    // ...
    'Fibers' => Kode\Fibers\Facades\Fibers::class,
],
```

> 注意：从 Laravel 5.5 开始，服务提供者和门面会自动注册，不需要手动添加。

### 使用方法

#### 通过门面使用

```php
use Fibers;

// 执行单个任务
$result = Fibers::run(fn() => doSomeWork());

// 使用纤程池
$pool = Fibers::pool();
$results = $pool->concurrent([
    fn() => fetchDataFromApi('https://api1.example.com'),
    fn() => fetchDataFromApi('https://api2.example.com'),
]);

// 创建通道
$channel = Fibers::channel('notifications', 100);
```

#### 通过依赖注入使用

```php
use Kode\Fibers\FiberPool;
use Kode\Fibers\Channel\ChannelManager;

class UserService
{
    private $fiberPool;
    private $channelManager;
    
    public function __construct(FiberPool $fiberPool, ChannelManager $channelManager)
    {
        $this->fiberPool = $fiberPool;
        $this->channelManager = $channelManager;
    }
    
    public function processBatch(array $users)
    {
        // 使用纤程池处理多个用户
        $tasks = array_map(fn($user) => fn() => $this->processUser($user), $users);
        return $this->fiberPool->concurrent($tasks);
    }
    
    public function notifyUsers(array $users)
    {
        // 获取通知通道
        $channel = $this->channelManager->get('notifications');
        
        // 发送通知到通道
        foreach ($users as $user) {
            $channel->push(['user_id' => $user->id, 'type' => 'welcome']);
        }
    }
}
```

#### 使用中间件

Kode/Fibers 提供了 Laravel 中间件，可以在 HTTP 请求处理过程中自动启用纤程：

```php
// app/Http/Kernel.php
protected $middleware = [
    // ...
    \Kode\Fibers\Http\Middleware\EnableFibers::class,
];

// 或者仅为特定路由组启用
protected $middlewareGroups = [
    'api' => [
        // ...
        \Kode\Fibers\Http\Middleware\EnableFibers::class,
    ],
];
```

### Laravel 特定配置

在 `config/fibers.php` 中，可以配置 Laravel 特定的选项：

```php
return [
    // ... 其他配置 ...
    
    'laravel' => [
        'middleware' => [
            'enable_fibers' => true,  // 是否启用纤程中间件
            'timeout_middleware' => true,  // 是否启用超时中间件
        ],
        'commands' => true,  // 是否启用 Artisan 命令
        'queue' => [
            'enable_fiber_worker' => true,  // 是否启用纤程队列工作器
            'connection' => 'redis',  // 用于纤程队列的连接
        ],
    ],
];
```

### Artisan 命令

Kode/Fibers 提供了几个 Artisan 命令：

```bash
# 初始化配置
php artisan fibers:init

# 查看状态
php artisan fibers:status

# 性能测试
php artisan fibers:benchmark
```

## Symfony 集成

Kode/Fibers 提供了 Symfony 捆绑包，可以轻松集成到 Symfony 应用中。

### 安装步骤

1. 安装 Kode/Fibers

```bash
composer require kode/fibers
```

2. 注册捆绑包（Symfony 4.0+ 会自动注册）

```php
// config/bundles.php
return [
    // ...
    Kode\Fibers\Bridge\Symfony\KodeFibersBundle::class => ['all' => true],
];
```

3. 创建配置文件

```bash
php bin/console fibers:install
```

这将在 `config/packages` 目录下创建 `fibers.yaml` 配置文件。

### 使用方法

#### 通过服务容器使用

```php
use Kode\Fibers\FiberPool;
use Kode\Fibers\Channel\ChannelManager;

class UserController extends AbstractController
{
    public function index(FiberPool $fiberPool, ChannelManager $channelManager)
    {
        // 使用纤程池
        $results = $fiberPool->concurrent([
            fn() => $this->fetchData('https://api1.example.com'),
            fn() => $this->fetchData('https://api2.example.com'),
        ]);
        
        // 使用通道
        $channel = $channelManager->get('notifications');
        $channel->push(['message' => 'Operation completed']);
        
        return $this->json($results);
    }
}
```

#### 使用命令行工具

```bash
# 查看帮助
php bin/console kode:fibers --help

# 初始化配置
php bin/console kode:fibers init

# 查看状态
php bin/console kode:fibers status
```

### Symfony 特定配置

在 `config/packages/fibers.yaml` 中，可以配置 Symfony 特定的选项：

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
        console_commands: true
```

## Yii3 集成

Kode/Fibers 提供了 Yii3 模块，可以集成到 Yii3 应用中。

### 安装步骤

1. 安装 Kode/Fibers

```bash
composer require kode/fibers
```

2. 在 `config/common.php` 或 `config/web.php` 中注册模块

```php
return [
    'modules' => [
        'fibers' => Kode\Fibers\Bridge\Yii\Module::class,
    ],
];
```

3. 初始化配置

```bash
php yii fibers/setup
```

### 使用方法

#### 通过依赖注入使用

```php
use Kode\Fibers\FiberPool;

class UserService
{
    private $fiberPool;
    
    public function __construct(FiberPool $fiberPool)
    {
        $this->fiberPool = $fiberPool;
    }
    
    public function processBatch(array $users)
    {
        $tasks = array_map(fn($user) => fn() => $this->processUser($user), $users);
        return $this->fiberPool->concurrent($tasks);
    }
}
```

#### 使用命令行工具

```bash
# 查看状态
php yii fibers/status

# 性能测试
php yii fibers/benchmark
```

## ThinkPHP8 集成

Kode/Fibers 提供了 ThinkPHP8 扩展，可以集成到 ThinkPHP8 应用中。

### 安装步骤

1. 安装 Kode/Fibers

```bash
composer require kode/fibers
```

2. 生成配置文件

```bash
php think fibers:config
```

这将在 `config` 目录下创建 `fibers.php` 配置文件。

3. （可选）在 `config/app.php` 中注册服务提供者

```php
'service_provider' => [
    // ...
    \Kode\Fibers\Bridge\ThinkPHP\ServiceProvider::class,
],
```

### 使用方法

#### 通过容器使用

```php
use think\App;

class UserController extends Controller
{
    public function index(App $app)
    {
        // 获取纤程池
        $fiberPool = $app->get('Kode\\Fibers\\FiberPool');
        
        // 使用纤程池
        $results = $fiberPool->concurrent([
            fn() => $this->fetchData('https://api1.example.com'),
            fn() => $this->fetchData('https://api2.example.com'),
        ]);
        
        return json($results);
    }
}
```

#### 使用命令行工具

```bash
# 查看帮助
php think fibers --help

# 查看状态
php think fibers:status
```

## 通用框架/原生 PHP 集成

如果您使用的是自定义框架或原生 PHP，可以通过以下方式集成 Kode/Fibers。

### 安装步骤

1. 安装 Kode/Fibers

```bash
composer require kode/fibers
```

2. 初始化配置

```bash
php vendor/bin/fibers init
```

这将在当前目录下创建 `fibers-config.php` 配置文件。

### 使用方法

#### 基本用法

```php
use Kode\Fibers\Fibers;
use Kode\Fibers\FiberPool;
use Kode\Fibers\Channel\Channel;

// 初始化配置
$config = require 'fibers-config.php';
Fibers::init($config);

// 使用 Facade
$result = Fibers::run(fn() => doSomeWork());

// 直接使用类
$pool = new FiberPool(['size' => 16]);
$results = $pool->concurrent([...]);

$channel = new Channel(10);
$channel->push($data);
$received = $channel->pop();
```

#### 自定义容器集成

如果您使用自定义的依赖注入容器，可以按照以下方式集成：

```php
// 注册服务到容器
$container->set('FiberPool', function () {
    return new Kode\Fibers\FiberPool([
        'size' => 16,
        'max_exec_time' => 30
    ]);
});

$container->set('ChannelManager', function () {
    return new Kode\Fibers\Channel\ChannelManager();
});

// 从容器获取服务
$pool = $container->get('FiberPool');
```

## 框架集成最佳实践

### 配置管理

- 为每个环境（开发、测试、生产）使用不同的配置
- 合理设置池大小，通常为 CPU 核心数的 2-4 倍
- 为长时间运行的任务设置适当的超时时间

### 依赖注入

- 优先使用依赖注入获取 Kode/Fibers 的服务，而不是直接实例化
- 对于 Laravel、Symfony、Yii3 等框架，利用框架的服务容器和自动注入功能
- 对于自定义框架，实现自己的服务提供者或工厂类

### 中间件和过滤器

- 使用框架提供的中间件机制在请求生命周期中启用纤程
- 实现自定义中间件处理纤程相关的异常和超时
- 对于 API 应用，考虑使用纤程处理并发请求

### 队列和任务处理

- 结合框架的队列系统使用纤程池提高处理效率
- 对于 Laravel，可以使用 `php artisan queue:work --use-fibers` 命令启用纤程工作器
- 对于其他框架，实现自定义的队列处理器，利用 Kode/Fibers 的功能

### 性能考虑

- 避免在纤程中执行阻塞操作
- 对于 I/O 密集型任务，优先使用异步驱动
- 监控纤程池的状态，及时调整配置
- 考虑使用连接池（如数据库连接池）与纤程池结合使用

## 常见问题

### Q: 我使用的框架不在上面列出的范围内，如何集成？

A: Kode/Fibers 设计为框架无关的库，您可以按照"通用框架/原生 PHP 集成"部分的说明进行集成。如果您需要更深入的集成，可以参考已有的框架集成代码，实现自己的服务提供者或扩展。

### Q: 在框架中使用纤程时，如何处理异常？

A: 与处理普通 PHP 异常类似，您可以使用 try/catch 捕获纤程中抛出的异常。对于框架集成，您还可以利用框架的异常处理机制，注册全局异常处理器。

### Q: 如何在框架的事件系统中使用纤程？

A: 您可以在事件监听器中使用纤程处理耗时操作，例如：

```php
Event::listen('user.registered', function ($event) {
    Fibers::run(function () use ($event) {
        // 发送欢迎邮件
        $this->sendWelcomeEmail($event->user);
        
        // 记录用户活动
        $this->logUserActivity($event->user);
    });
});
```

### Q: 在框架中使用纤程时，如何处理数据库事务？

A: 数据库事务与纤程的结合需要特别注意，因为每个纤程可能使用不同的数据库连接。建议：

- 为每个纤程分配独立的数据库连接
- 在单个纤程内完成事务操作，避免跨纤程的事务
- 对于需要跨纤程协调的操作，考虑使用消息队列或其他异步机制

## 下一步

- 查看 [任务管理](task-management.md) 文档了解如何管理和调度任务
- 查看 [环境检测](environment-checks.md) 文档了解如何检测和处理环境限制
- 查看 [高级示例](../examples/advanced_example.php) 了解更多框架集成的实际用法