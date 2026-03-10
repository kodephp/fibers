# 快速入门指南

本指南将帮助您快速上手 `Kode/fibers` 包，了解其核心功能和使用方法。

## 安装

使用 Composer 安装 `Kode/fibers` 包：

```bash
composer require Kode/fibers
```

## 基本使用

### 1. 一键启动纤程

最简单的使用方式是直接通过 `Fibers` 类运行单个纤程任务：

```php
use Kode\Fibers\Facades\Fiber;

// 启动一个纤程并等待结果
$result = Fiber::run(function () {
    // 在纤程中执行耗时操作
    usleep(100000); // 100ms
    return 'Hello from Fiber!';
});

echo $result; // 输出: Hello from Fiber!
```

### 2. 超时控制

您可以为纤程任务设置超时时间，防止任务执行时间过长：

```php
use Kode\Fibers\Facades\Fiber;

// 设置 500ms 超时
$result = Fiber::run(function () {
    // 模拟耗时操作
    usleep(1000000); // 1秒
    return 'This will not be returned';
}, 0.5);

// 超时后会抛出异常
// Kode\Fibers\Exceptions\TimeoutException: Fiber execution timed out after 0.5 seconds
```

### 3. 并行执行多个任务

使用 `parallel` 方法可以并行执行多个任务，提高效率：

```php
use Kode\Fibers\Facades\Fiber;

// 并行执行多个任务
$results = Fiber::parallel([
    function () {
        usleep(100000);
        return 'Result 1';
    },
    function () {
        usleep(200000);
        return 'Result 2';
    },
    function () {
        usleep(50000);
        return 'Result 3';
    }
]);

print_r($results); // 包含三个任务的结果，按原顺序返回
```

## 使用纤程池（推荐生产环境）

在生产环境中，建议使用纤程池来管理纤程资源，提高性能和稳定性：

### 1. 创建纤程池

```php
use Kode\Fibers\Core\FiberPool;

// 创建纤程池，设置大小为 16
$pool = new FiberPool([
    'size' => 16,                      // 纤程池大小
    'max_exec_time' => 30,            // 单任务最长执行时间（秒）
    'gc_interval' => 100,             // 每执行100次触发垃圾回收
    'name' => 'main-worker-pool'      // 池名称（用于调试）
]);
```

### 2. 使用纤程池执行任务

```php
// 在纤程池中执行单个任务
$result = $pool->run(function () {
    return file_get_contents('https://api.example.com/data');
});

// 并行执行多个任务
$results = $pool->concurrent([
    function () { return file_get_contents('https://api.a.com'); },
    function () { return file_get_contents('https://api.b.com'); },
    function () { return file_get_contents('https://api.c.com'); }
]);
```

### 3. 获取纤程池状态

```php
// 获取统计信息
$stats = $pool->getStats();

// 获取活跃纤程数
$activeCount = $pool->getActiveCount();

// 获取已执行任务总数
$totalTasks = $pool->getTotalTasks();
```

## 纤程间通信

### 1. 创建通道

```php
use Kode\Fibers\Channel\Channel;

// 创建一个无缓冲通道
$channel = new Channel('my-channel');

// 或者使用静态方法创建带缓冲的通道（缓冲区大小为10）
$bufferedChannel = Channel::make('buffered-channel', 10);
```

### 2. 基本通信

```php
use Kode\Fibers\Facades\Fiber;
use Kode\Fibers\Channel\Channel;

$ch = Channel::make('communication');

// 生产者
Fiber::run(function () use ($ch) {
    for ($i = 1; $i <= 5; $i++) {
        echo "Producing: \$i\n";
        $ch->push("Item $i");
        Fiber::sleep(0.2); // 纤程安全的睡眠
    }
    $ch->close(); // 关闭通道表示生产结束
});

// 消费者
Fiber::run(function () use ($ch) {
    while (true) {
        try {
            $item = $ch->pop();
            echo "Consumed: $item\n";
        } catch (\Kode\Fibers\Exceptions\ChannelClosedException $e) {
            echo "Channel closed, consumer exiting.\n";
            break;
        }
    }
});
```

### 3. 带超时的通信

```php
// 尝试1秒内接收消息，如果超时则返回默认值
try {
    $message = $ch->pop(1.0);
    // 处理消息
} catch (\RuntimeException $e) {
    // 处理超时
    echo "Timeout waiting for message.\n";
}

// 尝试1秒内发送消息，如果超时则返回失败
try {
    $success = $ch->push($data, 1.0);
    if (!$success) {
        echo "Failed to send message.\n";
    }
} catch (\RuntimeException $e) {
    // 处理超时
    echo "Timeout sending message.\n";
}
```

## 任务重试机制

对于可能失败的网络请求等操作，您可以使用任务重试机制：

```php
use Kode\Fibers\Facades\Fiber;

$result = Fiber::retry(function () {
    $response = file_get_contents('https://api.example.com/data');
    if (!$response) {
        throw new \Exception('API request failed');
    }
    return $response;
}, 3, 0.5); // 最多重试3次，每次间隔0.5秒
```

## 上下文管理

在纤程中，您可以使用上下文来共享数据：

```php
use Kode\Fibers\Context\Context;
use Kode\Fibers\Facades\Fiber;

// 设置上下文数据
Context::set('user_id', 123);
Context::set('request_id', 'abc-123');

// 在纤程中访问上下文
Fiber::run(function () {
    $userId = Context::get('user_id');
    $requestId = Context::get('request_id');
    echo "Processing request $requestId for user $userId\n";
});
```

## 框架集成

### Laravel 集成

1. 发布配置文件：

```bash
php artisan vendor:publish --tag=fibers-config
```

2. 在配置文件 `config/fibers.php` 中调整设置

3. 使用 Facade：

```php
use Kode\Fibers\Facades\Fiber;

// 在控制器或服务中使用
public function index()
{
    $results = Fiber::parallel([
        fn() => $this->fetchUserStats(),
        fn() => $this->fetchRecentActivity(),
        fn() => $this->fetchNotifications()
    ]);
    
    return view('dashboard', ['data' => $results]);
}
```

### 其他框架

对于其他框架，您可以使用通用的初始化方法：

```php
use Kode\Fibers\Providers\GenericProvider;

// 在应用启动时初始化
GenericProvider::init([
    'default_pool' => [
        'size' => 32,
        'timeout' => 30
    ],
    'channels' => [
        'default' => ['buffer_size' => 100]
    ]
]);
```

## 环境诊断

使用内置的诊断工具检查运行环境：

```php
use Kode\Fibers\Facades\Fiber;

// 诊断运行环境
$issues = Fiber::diagnose();

// 输出问题
foreach ($issues as $issue) {
    echo "⚠️ {$issue['type']}: {$issue['message']}\n";
}
```

## 命令行工具

`Kode/fibers` 提供了命令行工具来帮助您管理和使用纤程：

```bash
# 初始化配置文件
php vendor/bin/fibers init

# 查看状态
php vendor/bin/fibers status

# 运行基准测试
php vendor/bin/fibers benchmark

# 诊断环境
php vendor/bin/fibers diagnose
```

## 常见问题

### Q: 为什么在析构函数中无法使用纤程？

**A:** PHP 8.4 之前的版本不允许在对象析构方法执行期间切换纤程。`Kode/fibers` 会自动检测 PHP 版本并处理这种情况，您可以通过配置文件关闭严格检查：

```php
return [
    'strict_destruct_check' => false
];
```

### Q: 如何确定合适的纤程池大小？

**A:** 推荐将纤程池大小设置为 CPU 核心数的 2-4 倍。您可以使用 `CpuInfo::get()` 获取 CPU 核心数：

```php
use Kode\Fibers\Support\CpuInfo;

$cpuCount = CpuInfo::get();
$poolSize = $cpuCount * 3;

$pool = new FiberPool(['size' => $poolSize]);
```

### Q: 哪些函数在纤程中是不安全的？

**A:** 一些会阻塞主线程的函数在纤程中可能会导致性能问题，例如 `sleep()`、`file_get_contents()`（用于网络请求）等。`Kode/fibers` 提供了这些函数的安全替代方案，如 `Fiber::sleep()`。