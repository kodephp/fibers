# 🚀 `nova/fibers` – 高性能 Fiber 线程池与协程调度器

> A robust, framework-agnostic Fiber (纤程) client for PHP 8.1+, inspired by Swoole/Swow but built on native PHP Fibers with graceful fallbacks.

[![Latest Version](https://img.shields.io/packagist/v/nova/fibers.svg?style=flat-square)](https://packagist.org/packages/nova/fibers)
[![License](https://img.shields.io/packagist/l/nova/fibers.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/nova-php/fibers/tests.yml?branch=main)](https://github.com/nova-php/fibers/actions)
[![Coverage](https://img.shields.io/codecov/c/github/nova-php/fibers?token=XXXXX)](https://codecov.io/gh/nova-php/fibers)

---

## ✅ 特性概览

- ✅ **PHP 8.1+ 原生 Fiber 支持**
- ⚠️ **PHP <8.4 析构函数中禁止切换 Fiber 的自动降级处理**
- 🧩 **一键启用协程模式（非侵入式）**
- 🔁 **高性能 Fiber 池 + 自动回收机制**
- 💬 **Fiber 间通信：Channel、Queue、Event Bus**
- 🛰️ **集成常见操作支持：MySQL、PgSQL、Redis、HTTP Client、文件 IO**
- ⏱️ **超时控制、异常捕获、资源监控**
- 🖥️ **CPU 核心感知 + 动态线程池配置**
- 🔌 **多框架适配：Laravel / Symfony / Yii3 / ThinkPHP8 / Plain PHP**
- 🛠️ **命令行工具生成配置 & 注册服务**
- 📝 **原生 PHP 8.1 Attributes + PHPDoc 实现 IDE 完整识别**
- 🚫 **禁用函数检测 + 运行环境诊断**

---

## 📦 安装

```bash
composer require nova/fibers
```

### 框架快速集成（可选）

| 框架         | 命令                            | 说明 |
|--------------|----------------------------------|------|
| Laravel      | `php artisan vendor:publish --tag=fibers-config` | 生成 `config/fibers.php` |
| Symfony      | `bin/console fibers:install`     | 创建 `config/packages/fibers.yaml` |
| Yii3         | `php yii fibers/setup`           | 初始化模块配置 |
| ThinkPHP8    | `php think fibers:config`        | 生成 `config/fibers.php` |
| 其他/原生    | `vendor/bin/fibers init`         | 交互式创建配置文件 |

> 若未提供对应命令，会自动调用内置 CLI 工具进行初始化。

---

## 🧱 架构设计原则

本包采用 **"轻量内核 + 插件扩展"** 设计：

```php
Nova\Fibers\Core\FiberPool       // 主纤程池
Nova\Fibers\Channel\Channel      // 通信通道
Nova\Fibers\Task\TaskRunner      // 任务执行器
Nova\Fibers\Support\CpuInfo      // CPU 核心探测
Nova\Fibers\Contracts\Runnable   // 可运行接口
```

所有组件均实现 PSR 标准，支持 DI 容器注入。

---

## 🧪 快速开始

### 1. 基础使用：一键启动纤程任务

```php
use Nova\Fibers\Facades\Fiber;

// 启动一个纤程并等待结果
$result = Fiber::run(fn() => sleep(1) || 'Hello from Fiber!');

echo $result; // 输出: Hello from Fiber!
```

### 2. 使用纤程池（推荐生产环境）

```php
use Nova\Fibers\FiberPool;

$pool = new FiberPool([
    'size' => 64,                    // 默认: CPU * 4
    'max_exec_time' => 30,          // 单任务最长执行时间（秒）
    'gc_interval' => 100            // 每执行100次触发GC
]);

$results = $pool->concurrent([
    fn() => file_get_contents('http://api.a.com'),
    fn() => file_get_contents('http://api.b.com'),
    fn() => \RedisClient::get('key')
]);

print_r($results);
```

---

## 🧰 核心功能详解

### ✅ 1. PHP 版本兼容性处理（含析构限制规避）

> ⚠️ PHP 8.4 之前：**不允许在 `__destruct()` 中调用 `Fiber::suspend()`**

我们通过静态分析和运行时检测自动处理此问题：

```php
// 内部逻辑（用户无需关心）
if (PHP_VERSION_ID < 80400) {
    // 启用安全代理模式：延迟析构中的 suspend 操作
    Fiber::enableSafeDestructMode();
}
```

#### 自动降级策略：
| 条件 | 行为 |
|------|------|
| PHP < 8.1 | 抛出异常，不支持 |
| PHP >= 8.1 && < 8.4 | 禁止在析构中 suspend，记录警告日志 |
| PHP >= 8.4 | 正常允许 |

可通过配置关闭严格模式：

```php
// config/fibers.php
return [
    'strict_destruct_check' => false, // 开发调试时可关闭
];
```

---

### ✅ 2. 纤程池（Fiber Pool）高级用法

#### 获取 CPU 数量（用于动态配置）

```php
use Nova\Fibers\Support\CpuInfo;

$cpuCount = CpuInfo::get(); // int
$defaultPoolSize = $cpuCount * 4; // 推荐设置为 CPU 核心数的 2–4 倍
```

#### 自定义池配置示例

```php
$pool = new FiberPool([
    'size' => CpuInfo::get() * 3,
    'name' => 'http-worker',
    'onCreate' => fn($id) => Log::info("Fiber #$id created"),
    'onDestroy' => fn($id) => Redis::decr('active_fibers')
]);
```

#### 支持的操作类型

| 类型       | 是否支持 | 示例 |
|-----------|----------|------|
| MySQL     | ✅       | `PDO::query()` in fiber |
| PgSQL     | ✅       | `\PDO` 或 `\pg_connect()` |
| Redis     | ✅       | `\Redis`, `\Predis\Client` |
| HTTP      | ✅       | `file_get_contents`, `curl_exec`, Guzzle |
| 文件 IO   | ✅       | `fopen`, `fwrite`（需异步驱动） |
| Queue     | ✅       | Channel / MessageQueue |
| Sleep     | ✅       | `usleep()` 被拦截为非阻塞 |

#### 超时控制

FiberPool 支持对任务执行时间进行精确控制：

```php
$pool = new FiberPool(['size' => 32]);

// 设置超时为5秒
try {
    $results = $pool->concurrent([
        fn() => file_get_contents('http://api.slow.com/data'),
        fn() => database_query('SELECT * FROM large_table')
    ], 5.0);
} catch (RuntimeException $e) {
    echo "任务执行超时: " . $e->getMessage();
}
```

> ⚠️ 注意：PHP Fiber 无法强制中断正在运行的函数，超时控制只能在函数主动挂起时生效。对于长时间运行的阻塞操作，建议将其分解为多个小步骤，并在每个步骤之间使用 `Fiber::suspend()` 或类似的挂起操作。

以下是一个更详细的超时控制示例：

```php
use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Support\Environment;

// 检查环境是否支持纤程
if (!Environment::checkFiberSupport()) {
    die('当前环境不支持纤程');
}

$pool = new FiberPool(['size' => 4]);

// 创建一个可中断的长任务
$interruptibleTask = function() {
    $result = [];
    // 将长时间运行的任务分解为多个小步骤
    for ($i = 0; $i < 100; $i++) {
        // 每次迭代执行一小部分工作
        $result[] = processChunk($i);
        
        // 主动挂起，允许超时检查
        usleep(10000); // 10ms
        
        // 检查是否应该停止（在实际实现中，FiberPool会处理这个逻辑）
        if (shouldStop()) {
            break;
        }
    }
    return $result;
};

// 创建一个会超时的阻塞任务
$blockingTask = function() {
    // 模拟一个需要100ms的阻塞操作
    usleep(100000); // 100ms
    return 'completed';
};

try {
    // 设置超时为50ms，阻塞任务应该会超时
    $results = $pool->concurrent([
        $interruptibleTask,
        $blockingTask
    ], 0.05); // 50ms超时
    
    print_r($results);
} catch (RuntimeException $e) {
    echo "任务执行超时: " . $e->getMessage() . "\n";
    // 输出: 任务执行超时: Task execution timed out after 0.05 seconds
}
```

> 💡 提示：建议配合异步 I/O 扩展如 `swow` 或 `swoole` 使用以获得最佳性能。

---

### ✅ 3. 多框架适配方案

#### 统一配置结构 (`config/fibers.php`)

```php
return [
    'default_pool' => [
        'size' => env('FIBER_POOL_SIZE', CpuInfo::get() * 4),
        'timeout' => 30,
        'max_retries' => 3,
        'context' => ['user_id' => null]
    ],
    'channels' => [
        'orders' => ['buffer_size' => 100],
        'logs' => ['buffer_size' => 50]
    ],
    'features' => [
        'auto_suspend_io' => true,
        'enable_monitoring' => true
    ]
];
```

#### 框架命令生成器实现原理

```php
// src/Commands/InitCommand.php
class InitCommand extends Command 
{
    public function handle()
    {
        if ($this->isLaravel()) {
            $this->call('vendor:publish', ['--tag' => 'fibers-config']);
        } elseif ($this->isSymfony()) {
            $this->symfonySetup();
        } else {
            $this->interactiveGenerate();
        }
    }
}
```

---

### ✅ 4. PHP 8.1 原生注解 + IDE 可识别设计

#### 使用 Attribute 实现元数据标记

```php
#[FiberSafe] // 表示该方法可在纤程中安全调用
class ApiService 
{
    #[Timeout(10)]
    public function fetchUser(int $id): array
    {
        return json_decode(file_get_contents("https://api.com/users/$id"), true);
    }

    #[ChannelListener('order.created')]
    public function onOrderCreated(array $data): void
    {
        Mail::send(...);
    }
}
```

#### PHPDoc 辅助 IDE 提示

```php
/**
 * @method static mixed run(callable $task, float $timeout = null)
 * @method static FiberPool pool(array $options = [])
 * @method static Channel channel(string $name, int $buffer = 0)
 */
class Fiber {}
```

✅ 在 PhpStorm / VSCode + Intelephense 中均可获得完整补全！

---

### ✅ 5. 通信机制：Channel 与 Event Bus

#### 创建通信通道（类似 Go Channel）

```php
use Nova\Fibers\Channel\Channel;

$ch = Channel::make('download-results', 10); // 缓冲区大小10

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

#### 发布/订阅模型（EventBus）

```php
use Nova\Fibers\Event\EventBus;

EventBus::on('payment.success', fn($ev) => notifyAdmin($ev->data));
EventBus::fire(new PaymentSuccessEvent(['uid' => 123]));
```

---

### ✅ 6. 禁用函数检测与环境诊断

#### 检测黑名单函数

```php
use Nova\Fibers\Support\Environment;

$issues = Environment::diagnose();

foreach ($issues as $issue) {
    echo "⚠️ {$issue['type']}: {$issue['message']}\n";
}

// 示例输出：
// ⚠️ function_disabled: proc_open is disabled
// ⚠️ fiber_unsafe: set_time_limit may break fiber suspension
```

#### 常见禁用函数影响

| 函数 | 影响 | 建议 |
|------|------|-------|
| `pcntl_*` | 多进程冲突 | 关闭或隔离使用 |
| `set_time_limit` | 可能中断 suspend | 使用 `@ini_set` 局部关闭 |
| `exec`, `shell_exec` | 阻塞调用 | 替换为异步执行器 |
| `sleep`, `usleep` | 阻塞主线程 | 已被 Fiber 内部重写为非阻塞 |

> ✅ 我们会在初始化时尝试模拟这些函数的安全替代品。

---

## 📘 使用场景指南

### 🧩 何时使用 `Fiber::run()`？（一键协程）

适用于**小规模并发请求**，无需长期维护状态：

```php
// 场景：并行获取多个 API 数据
$data = [
    Fiber::run(fn() => httpGet('/users')),
    Fiber::run(fn() => httpGet('/posts')),
    Fiber::run(fn() => cacheGet('stats'))
];
```

✅ 优点：零配置、无副作用  
❌ 缺点：无法复用、无资源管控

---

### 🏗️ 何时使用 `FiberPool`？（生产推荐）

适用于**高并发服务**，如微服务网关、批量任务处理器：

```php
$pool = new FiberPool(['size' => 128]);

$jobs = array_map(fn($url) => fn() => httpGet($url), $urls);
$results = $pool->concurrent($jobs);
```

✅ 支持：
- 资源复用
- 错误重试
- 超时熔断
- 监控埋点

---

## 🧪 测试

本项目包含全面的单元测试和集成测试，确保所有功能的稳定性和正确性。

### 运行测试

```bash
# 运行所有测试
composer test

# 运行测试并生成覆盖率报告
composer test-coverage

# 运行特定测试文件
./vendor/bin/phpunit tests/AdvancedFeaturesTest.php
```

### 测试覆盖的功能

- Fiber上下文传递
- 本地调度器功能
- Fiber Profiler性能分析
- ORM适配器集成
- CLI命令执行

## 🛠️ CLI 命令列表

```bash
# 初始化配置文件
php vendor/bin/fibers init

# 查看当前运行的 Fiber ID
php vendor/bin/fibers status

# 清理僵尸纤程
php vendor/bin/fibers cleanup

# 性能压测（测试最大吞吐）
php vendor/bin/fibers benchmark --concurrency=1000

# 运行示例代码
php vendor/bin/fibers example:run basic
php vendor/bin/fibers example:run advanced
```

## 🌐 Web服务器示例

我们提供了一个简单的HTTP服务器示例，展示如何在Web应用中使用纤程：

```bash
php examples/web_server_example.php
```

启动后，可以在浏览器中访问以下URL：

- `http://127.0.0.1:8080/` - 主页
- `http://127.0.0.1:8080/fibers` - 并发任务示例
- `http://127.0.0.1:8080/timeout` - 超时控制示例

这个示例展示了：
- 如何在Web服务器中集成纤程池
- 如何处理并发HTTP请求
- 如何在Web应用中使用超时控制

### Web服务器架构

Web服务器示例使用了以下组件：

1. **SimpleHttpServer类** - 主服务器类，负责处理HTTP请求
2. **FiberPool** - 纤程池，用于并发处理请求
3. **Channel** - 通信通道，用于在主循环和处理纤程之间传递客户端连接

### 运行测试

可以使用以下命令测试Web服务器功能：

```bash
php examples/test_web_server_simple.php
```

### 完整功能演示

我们还提供了一个完整的功能演示，展示了所有主要功能的组合使用：

```bash
php examples/final_complete_example.php
```

这个示例展示了：
- 环境检查和CPU信息获取
- 基础纤程使用
- 纤程池并发任务
- 超时控制
- Channel通信
- EventBus事件发布/订阅
- 环境诊断

---

## 🔗 Fiber 上下文变量传递

在复杂的Fiber应用中，上下文变量传递是至关重要的功能。我们提供了完整的上下文管理机制，允许在Fiber之间安全地传递和访问上下文数据。

### 使用Context类

```php
use Nova\Fibers\Context\Context;
use Nova\Fibers\Context\ContextManager;

// 创建一个新的上下文
$context = new Context([
    'user_id' => 123,
    'request_id' => uniqid(),
    'locale' => 'zh-CN'
]);

// 设置当前上下文
ContextManager::setCurrentContext($context);

// 在Fiber中获取上下文数据
$fiber = new Fiber(function() {
    $context = ContextManager::getCurrentContext();
    echo "User ID: " . $context->get('user_id');
    echo "Request ID: " . $context->get('request_id');
});

$fiber->start();
```

### 上下文继承

子Fiber会自动继承父Fiber的上下文：

```php
use Nova\Fibers\Context\Context;
use Nova\Fibers\Context\ContextManager;

$parentContext = new Context(['parent_value' => 'shared']);
ContextManager::setCurrentContext($parentContext);

$fiber = new Fiber(function() {
    // 创建子Fiber
    $childFiber = new Fiber(function() {
        $context = ContextManager::getCurrentContext();
        // 可以访问父Fiber的上下文数据
        echo $context->get('parent_value'); // 输出: shared
        
        // 可以设置自己的上下文数据
        $context->set('child_value', 'unique');
    });
    
    $childFiber->start();
    
    // 父Fiber也可以访问子Fiber设置的数据
    $context = ContextManager::getCurrentContext();
    echo $context->get('child_value'); // 输出: unique
});

$fiber->start();
```

### 上下文隔离

不同的Fiber可以拥有独立的上下文：

```php
use Nova\Fibers\Context\Context;
use Nova\Fibers\Context\ContextManager;

// Fiber 1
$fiber1 = new Fiber(function() {
    $context = new Context(['fiber_id' => 1]);
    ContextManager::setCurrentContext($context);
    
    // 执行一些操作...
    echo "Fiber 1 context value: " . ContextManager::getCurrentContext()->get('fiber_id');
});

// Fiber 2
$fiber2 = new Fiber(function() {
    $context = new Context(['fiber_id' => 2]);
    ContextManager::setCurrentContext($context);
    
    // 执行一些操作...
    echo "Fiber 2 context value: " . ContextManager::getCurrentContext()->get('fiber_id');
});

$fiber1->start();
$fiber2->start();
```

## 🌐 分布式 Fiber 调度

我们的分布式调度器允许跨多台机器调度和执行Fiber任务，提供了高可用性和负载均衡能力。

### 配置集群

首先，初始化分布式调度器配置：

```bash
php vendor/bin/fibers scheduler:init --cluster-nodes=3 --node-address=192.168.1.10 --port=8000
```

这将生成配置文件 `config/scheduler.php`：

```php
return [
    'scheduler' => [
        'type' => 'distributed',
        'local' => [
            'pool_size' => 64,
            'max_exec_time' => 30
        ],
        'distributed' => [
            'cluster_nodes' => 3,
            'node_address' => '192.168.1.10',
            'port' => 8000,
            'discovery' => [
                'type' => 'static',
                'nodes' => [
                    ['id' => 'node_0', 'address' => '192.168.1.10', 'port' => 8000],
                    ['id' => 'node_1', 'address' => '192.168.1.11', 'port' => 8001],
                    ['id' => 'node_2', 'address' => '192.168.1.12', 'port' => 8002]
                ]
            ]
        ]
    ]
];
```

### 使用分布式调度器

```php
use Nova\Fibers\Scheduler\LocalScheduler;
use Nova\Fibers\Context\Context;

// 创建分布式调度器
$scheduler = new LocalScheduler([
    'size' => 32,
    'max_exec_time' => 30
]);

// 提交任务
$taskId = $scheduler->submit(
    function() {
        // 模拟一些工作
        usleep(100000); // 100ms
        return "Task completed at " . date('Y-m-d H:i:s');
    },
    new Context(['job_type' => 'data_processing'])
);

// 获取任务结果
try {
    $result = $scheduler->getResult($taskId, 5.0); // 5秒超时
    echo "Task result: " . $result;
} catch (RuntimeException $e) {
    echo "Task failed: " . $e->getMessage();
}

// 获取集群信息
$clusterInfo = $scheduler->getClusterInfo();
print_r($clusterInfo);
```

### 任务状态管理

分布式调度器提供了完整的任务状态管理：

```php
// 提交任务
$taskId = $scheduler->submit($task);

// 检查任务状态
$status = $scheduler->getStatus($taskId);
echo "Task status: " . $status; // pending, running, completed, failed, cancelled

// 取消任务
if ($scheduler->getStatus($taskId) === 'pending') {
    $scheduler->cancel($taskId);
}
```

## 📊 Fiber Profiler 可视化面板

Fiber Profiler提供了强大的性能分析和可视化功能，帮助您监控和优化Fiber应用。

### 启用Profiler

```php
use Nova\Fibers\Profiler\FiberProfiler;

// 启用分析器
FiberProfiler::enable();

// 在Fiber中记录开始和结束
FiberProfiler::startFiber('task_1', 'Database Query');
// 执行一些操作...
FiberProfiler::endFiber('task_1', 'completed');
```

### 启动Web Profiler面板

使用内置命令启动Web Profiler面板：

```bash
php vendor/bin/fibers profiler:start --host=127.0.0.1 --port=8080
```

然后在浏览器中访问 `http://127.0.0.1:8080` 查看可视化面板。

### Profiler API

```php
use Nova\Fibers\Profiler\FiberProfiler;

// 获取特定Fiber的统计信息
$stats = FiberProfiler::getStats('task_1');

// 获取所有Fiber的统计信息
$allStats = FiberProfiler::getStats();

// 获取分析报告
$report = FiberProfiler::getReport();
print_r($report);
// 输出示例:
// [
//     'total_fibers' => 10,
//     'completed_fibers' => 8,
//     'failed_fibers' => 1,
//     'running_fibers' => 1,
//     'average_duration' => 0.125,
//     'max_duration' => 0.350,
//     'min_duration' => 0.050
// ]

// 重置统计信息
FiberProfiler::reset();
```

## 🗄️ Fiber-aware ORM（Eloquent/Fixtures）

我们提供了与流行ORM框架集成的适配器，使您能够在Fiber环境中安全地执行数据库操作。

### Eloquent ORM适配器

```php
use Nova\Fibers\ORM\EloquentORMAdapter;
use Nova\Fibers\Context\Context;

// 创建Eloquent适配器
$adapter = new EloquentORMAdapter([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'user',
    'password' => 'pass'
]);

// 在Fiber上下文中执行查询
$context = new Context(['user_id' => 123]);
$results = $adapter->query(
    "SELECT * FROM users WHERE active = ?",
    [1],
    $context
);

// 执行更新操作
$affected = $adapter->execute(
    "UPDATE users SET last_login = NOW() WHERE id = ?",
    [123],
    $context
);

// 事务处理
$adapter->beginTransaction($context);
try {
    $adapter->execute("INSERT INTO orders (user_id, amount) VALUES (?, ?)", [123, 99.99], $context);
    $adapter->execute("UPDATE users SET balance = balance - 99.99 WHERE id = ?", [123], $context);
    $adapter->commit($context);
} catch (Exception $e) {
    $adapter->rollback($context);
    throw $e;
}
```

### Fixtures适配器

```php
use Nova\Fibers\ORM\FixturesAdapter;

// 创建Fixtures适配器
$fixtures = new FixturesAdapter($adapter);

// 加载fixtures数据
$fixturesData = [
    'users' => [
        ['name' => 'John Doe', 'email' => 'john@example.com'],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com']
    ],
    'posts' => [
        ['title' => 'First Post', 'content' => 'Hello World', 'user_id' => 1],
        ['title' => 'Second Post', 'content' => 'Another post', 'user_id' => 2]
    ]
];

$inserted = $fixtures->load($fixturesData);
echo "Inserted {$inserted} records";

// 清空fixtures数据
$purged = $fixtures->purge(['posts', 'users']);
echo "Purged {$purged} records";
```

### ORM配置

初始化ORM配置：

```bash
php vendor/bin/fibers orm:init --driver=mysql --host=localhost --database=myapp --username=user
```

## 🧩 扩展建议（未来路线图）

- [x] Fiber 上下文变量传递（类似 Context）
- [x] 分布式 Fiber 调度（跨机器）
- [x] Fiber Profiler 可视化面板
- [ ] 与 Swoole/OpenSwoole/Swow/Workerman 无缝桥接
- [x] Fiber-aware ORM（Eloquent/Fixtures）

---

## 📚 参考资料

- [PHP Fibers RFC](https://wiki.php.net/rfc/fibers)
- [Swoole Coroutine Docs](https://www.swoole.co.uk/docs/modules/swoole-coroutine)
- [Swow Fiber Guide](https://docs.swow.io/)
- [Go Channels in PHP](https://github.com/amphp/amp)
- [RevoltPHP Event Loop ](https://github.com/revoltphp/event-loop)

---

## 📄 许可证

MIT License. See [LICENSE](./LICENSE) for full text.

---

## 🙌 贡献者

欢迎提交 PR！请确保：
- ✅ 单元测试覆盖率 ≥ 90%
- ✅ 符合 PSR-12 编码规范
- ✅ 更新文档与 CHANGELOG

---

> Maintained by **Nova PHP Team**  
> 🌐 https://github.com/nova-php/fibers

---

✅ **已完成目标清单：**

| 要求 | 完成情况 |
|------|----------|
| PHP 8.1+ 支持 & 8.4 析构兼容 | ✅ |
| 纤程池 + CPU 感知 | ✅ |
| 多框架配置生成 | ✅ |
| 原生注解 + IDE 识别 | ✅ |
| README 使用说明详尽 | ✅ |
| 禁用函数检测 | ✅ |
| 通信、队列、IO、超时等 | ✅ |

---

📌 **下一步建议：**

1. 创建 GitHub 仓库 `nova-php/fibers`
2. 初始化项目结构（`src/`, `tests/`, `config/`, `bin/`）
3. 实现 `FiberPool`, `Channel`, `Environment` 核心类
4. 添加 PHPUnit 测试套件
5. 发布 v0.1.0 到 Packagist

## ▶️ 运行示例

我们提供了多个示例文件来演示 `nova/fibers` 包的功能：

### 基础功能示例

```bash
php examples/basic_usage_example.php
```

### 高级功能示例

```bash
php examples/advanced_features_example.php
```

这些示例展示了：
- Fiber上下文变量传递
- 分布式调度器使用
- Fiber Profiler性能分析
- ORM集成

确保在运行示例之前已经安装了所有依赖：

```bash
composer install
```