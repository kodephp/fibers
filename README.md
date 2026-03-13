# 🚀 Kode/fibers – 高性能 Fiber 线程池与协程调度器

> 面向 PHP 8.1+ 的高性能 Fiber 纤程客户端，兼容主流框架并提供可降级、可诊断、可扩展的并发执行能力。

***

## ✅ 特性概览

- ✅ **PHP 8.1+ 原生 Fiber 支持**
- ⚠️ **PHP <8.4 析构函数中禁止切换 Fiber 的自动降级处理**
- 🧩 **一键启用协程模式（非侵入式）**
- 🔁 **高性能 Fiber 池 + 自动回收机制**
- 💬 **Fiber 间通信：Channel、Queue、Event Bus**
- 🛰️ **集成常见操作支持：MySQL、PgSQL、Redis、HTTP Client、文件 IO**
- ⏱️ **超时控制、异常捕获、资源监控**
- 🔄 **任务重试机制**
- 🖥️ **CPU 核心感知 + 动态线程池配置**
- 🔌 **多框架适配：Laravel / Symfony / Yii3 / ThinkPHP8 / Plain PHP**
- 🛠️ **命令行工具生成配置 & 注册服务**
- 📝 **原生 PHP 8.1 Attributes + PHPDoc 实现 IDE 完整识别**
- 🚫 **禁用函数检测 + 运行环境诊断**

## 📖 项目背景

`kode/fibers` 旨在为 Laravel、Symfony、Yii3、ThinkPHP8 及自建框架提供统一的纤程运行时能力，减少业务接入并发模型时的改造成本。\
项目重点解决三类问题：PHP 版本差异（尤其是 PHP<8.4 的析构限制）、生产场景下的并发治理（池化、超时、重试、通信）、以及跨框架可移植性（统一配置与 CLI 初始化）。

## 🗺️ 未来路线图

- 上下文传递机制：增强纤程上下文跨任务传播能力
- 分布式 Fiber 调度：支持跨机器调度与故障转移
- 性能监控面板：Profiler 可视化监控能力
- 生态系统集成：Swoole / OpenSwoole / Swow / Workerman 桥接
- ORM 适配层：Fiber-aware Eloquent / Doctrine 适配
- 断路器模式：自动熔断、半开恢复与保护策略
- 负载均衡：智能任务分发与动态并发控制
- 热重载支持：不中断服务更新代码
- 可视化管理界面：Web UI 管理池、队列、任务
- 更多框架支持：持续扩展框架接入

详细阶段计划见 [路线图文档](docs/roadmap.md)。

## ⚙️ PHP 8.5 兼容与便捷 API

新增便捷入口以降低接入成本并兼容未来 PHP 8.5 运行时能力：

```php
use Kode\Fibers\Fibers;

$result = Fibers::go(fn() => 'hello');

$ctxResult = Fibers::withContext(
    ['trace_id' => 'trace-001'],
    fn() => \Kode\Context\Context::get('trace_id')
);

$batch = Fibers::batch([1, 2, 3], fn(int $item) => $item * 2, 2);
$features = Fibers::runtimeFeatures();
```

### 新增健壮架构 API

- 上下文并发透传：`Fibers::concurrentWithContext()`
- 健壮单任务：`Fibers::resilientRun()`
- 远程调度分发：`Fibers::scheduleDistributedRemote()`
- 运行时桥接：`Fibers::runtimeBridgeInfo()`、`Fibers::runOnBridge()`
- 可视化监控：`Fibers::profile()`、`Fibers::profilerDashboard()`
- ORM 适配层：`Fibers::eloquent()`、`Fibers::fixtures()`

对应文档：

- [上下文传递机制](docs/context-propagation.md)
- [运行时桥接](docs/runtime-bridge.md)
- [Profiler 可视化面板](docs/profiler-dashboard.md)
- [ORM 适配层](docs/orm-adapters.md)

***

## 📦 安装

```bash
composer require Kode/fibers
```

### 框架快速集成（可选）

使用内置命令工具初始化框架配置：

```bash
# 自动检测框架类型并生成配置
php vendor/bin/fibers init

# 指定框架类型
php vendor/bin/fibers init --framework=laravel
```

各框架特定命令：

| 框架        | 命令                                               | 说明                               |
| --------- | ------------------------------------------------ | -------------------------------- |
| Laravel   | `php artisan vendor:publish --tag=fibers-config` | 生成 `config/fibers.php`           |
| Symfony   | `bin/console fibers:install`                     | 创建 `config/packages/fibers.yaml` |
| Yii3      | `php yii fibers/setup`                           | 初始化模块配置                          |
| ThinkPHP8 | `php think fibers:config`                        | 生成 `config/fibers.php`           |
| 其他/原生     | `vendor/bin/fibers init`                         | 交互式创建配置文件                        |

### 环境要求检查

安装完成后，可以运行诊断命令检查环境兼容性：

```bash
# 运行环境诊断
php vendor/bin/fibers diagnose
```

诊断结果将显示PHP版本、禁用函数、必要扩展等信息，并给出优化建议。

***

## 🧱 架构设计原则

本包采用 **“轻量内核 + 插件扩展”** 设计：

```php
Kode\Fibers\Core\FiberPool       // 主纤程池
Kode\Fibers\Channel\Channel      // 通信通道
Kode\Fibers\Task\TaskRunner      // 任务执行器
Kode\Fibers\Support\CpuInfo      // CPU 核心探测
Kode\Fibers\Contracts\Runnable   // 可运行接口
```

所有组件均实现 PSR 标准，支持 DI 容器注入。

***

## 🧪 快速开始

### 1. 基础使用：一键启动纤程任务

使用门面或辅助函数快速启动纤程：

```php
use Kode\Fibers\Facades\Fiber;

// 使用门面
$result = Fiber::run(fn() => {
    // 模拟异步操作
    usleep(100000);
    return 'Hello from Fiber!';
});

// 或使用辅助函数
$result = fiber_run(fn() => {
    usleep(100000);
    return 'Hello from Fiber!';
});

echo $result; // 输出: Hello from Fiber!
```

### 2. 使用纤程池（推荐生产环境）

纤程池支持资源复用、超时控制和错误重试：

```php
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Support\CpuInfo;

// 创建纤程池，自动检测CPU核心数
$pool = new FiberPool([
    'size' => CpuInfo::getRecommendedPoolSize(4),  // CPU核心数 * 4
    'max_exec_time' => 30,                         // 单任务最长执行时间（秒）
    'gc_interval' => 100,                          // 每执行100次触发GC
    'max_retries' => 3,                            // 自动重试次数
    'retry_delay' => 0.5                           // 重试间隔（秒）
]);

// 并行执行多个任务
$results = $pool->concurrent([
    fn() => file_get_contents('http://api.a.com'),
    fn() => file_get_contents('http://api.b.com'),
    fn() => \RedisClient::get('key')
], 10); // 总超时10秒

print_r($results);

// 使用事件回调监控任务状态
$pool = new FiberPool([
    'onTaskStart' => fn($task) => logger()->info('Task started'),
    'onTaskComplete' => fn($task, $result) => logger()->info('Task completed'),
    'onTaskFail' => fn($task, $error) => logger()->error('Task failed', ['error' => $error->getMessage()])
]);
```

***

## 🧰 核心功能详解

### ✅ 1. PHP 版本兼容性处理（含析构限制规避）

> ⚠️ PHP 8.4 之前：**不允许在** **`__destruct()`** **中调用** **`Fiber::suspend()`**

我们通过静态分析和运行时检测自动处理此问题，确保在不同PHP版本中都能正常运行：

```php
// 内部实现示例（用户无需关心）
if (PHP_VERSION_ID < 80400) {
    // 启用延迟析构任务队列，避免直接在析构函数中suspend
    Fibers::enableSafeDestructMode();
}
```

#### 自动降级策略：

| 条件                  | 行为                           |
| ------------------- | ---------------------------- |
| PHP < 8.1           | 抛出异常，不支持                     |
| PHP >= 8.1 && < 8.4 | 启用延迟析构任务队列，安全处理析构中的suspend操作 |
| PHP >= 8.4          | 正常允许在析构函数中使用suspend操作        |

可通过配置关闭严格模式：

```php
// config/fibers.php
return [
    'strict_destruct_check' => false, // 开发调试时可关闭
];
```

### ✅ 2. 纤程池（Fiber Pool）高级用法

#### 获取 CPU 数量（用于动态配置）

```php
use Kode\Fibers\Support\CpuInfo;

$cpuCount = CpuInfo::get(); // 获取系统CPU核心数
$recommendedSize = CpuInfo::getRecommendedPoolSize(4); // CPU核心数 × 4
```

#### 自定义池配置示例

```php
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Support\CpuInfo;

$pool = new FiberPool([
    'size' => CpuInfo::getRecommendedPoolSize(3), // CPU × 3
    'name' => 'http-worker',
    'max_exec_time' => 30, // 单任务超时时间
    'max_retries' => 3, // 失败重试次数
    'retry_delay' => 0.5, // 重试间隔（秒）
    'gc_interval' => 100, // GC触发间隔
    'concurrent_limit' => 500, // 最大并发任务数
    'onCreate' => fn($id) => logger()->info("Fiber #$id created"),
    'onDestroy' => fn($id) => logger()->info("Fiber #$id destroyed"),
    'onTaskStart' => fn($taskId) => logger()->debug("Task $taskId started"),
    'onTaskComplete' => fn($taskId, $result) => logger()->debug("Task $taskId completed"),
    'onTaskFail' => fn($taskId, $error) => logger()->error("Task $taskId failed: {$error->getMessage()}")
]);

// 执行单个任务
$singleResult = $pool->run(fn() => file_get_contents('https://api.example.com'));

// 并行执行多个任务
$results = $pool->concurrent([
    fn() => file_get_contents('https://api.a.com'),
    fn() => file_get_contents('https://api.b.com'),
    fn() => file_get_contents('https://api.c.com')
], 10); // 总超时10秒

// 获取池状态信息
$stats = $pool->getStats();
print_r($stats);
/*
输出示例：
Array(
    [active_fibers] => 12
    [total_tasks] => 156
    [completed_tasks] => 144
    [failed_tasks] => 3
    [average_execution_time] => 0.125
)*/

// 关闭池并释放资源
$pool->shutdown();

// 优雅关闭（等待当前任务完成）
$pool->shutdown(true);
```

#### 支持的操作类型

| 类型    | 是否支持 | 示例                                       |
| ----- | ---- | ---------------------------------------- |
| MySQL | ✅    | `PDO::query()` in fiber                  |
| PgSQL | ✅    | `\PDO` 或 `\pg_connect()`                 |
| Redis | ✅    | `\Redis`, `\Predis\Client`               |
| HTTP  | ✅    | `file_get_contents`, `curl_exec`, Guzzle |
| 文件 IO | ✅    | `fopen`, `fwrite`（需异步驱动）                 |
| Queue | ✅    | Channel / MessageQueue                   |
| Sleep | ✅    | `usleep()` 被拦截为非阻塞                       |

> 💡 提示：建议配合异步 I/O 扩展如 `swow` 或 `swoole` 使用以获得最佳性能。

### ✅ 3. 多框架适配方案

Kode/fibers提供了统一的API和自动检测机制，可以无缝集成到各种PHP框架中：

#### 统一配置结构 (`config/fibers.php`)

```php
return [
    // 默认纤程池配置
    'default_pool' => [
        'size' => env('FIBER_POOL_SIZE', CpuInfo::getRecommendedPoolSize(4)),
        'timeout' => 30,
        'max_retries' => 3,
        'retry_delay' => 0.5,
        'context' => ['user_id' => null]
    ],
    // 通信通道配置
    'channels' => [
        'orders' => ['buffer_size' => 100],
        'logs' => ['buffer_size' => 50]
    ],
    // 功能开关
    'features' => [
        'auto_suspend_io' => true,
        'enable_monitoring' => true,
        'strict_destruct_check' => env('APP_ENV') === 'production'
    ],
    // 环境检测配置
    'environment' => [
        'check_disabled_functions' => true,
        'required_extensions' => ['curl', 'mbstring'],
        'warn_only' => env('APP_ENV') !== 'production'
    ],
    'framework' => [
        'name' => env('APP_FRAMEWORK', 'laravel'),
        'service_provider' => true,
        'provider_class' => env('FIBER_PROVIDER_CLASS', 'Kode\\Fibers\\Providers\\LaravelServiceProvider'),
    ],
];
```

#### 框架集成示例

**Laravel 集成:**

```php
// 在 app/Providers/AppServiceProvider.php 中注册
public function register()
{
    $this->app->singleton(FiberPool::class, function () {
        return new FiberPool(config('fibers.default_pool'));
    });
}

// 使用
public function index(FiberPool $pool)
{
    $results = $pool->concurrent([...]);
    return response()->json($results);
}
```

**Symfony 集成:**

```yaml
# config/packages/fibers.yaml
kode_fibers:
    default_pool:
        size: 64
        timeout: 30
```

```php
// 在控制器中使用
public function index(FiberPool $pool)
{
    $results = $pool->concurrent([...]);
    return $this->json($results);
}
```

**Yii3 框架集成:**

```php
// 在 config/common.php 中配置
return [
    'components' => [
        'fibers' => [
            'class' => \Kode\Fibers\Core\FiberManager::class,
            'poolConfig' => $params['fibers.default_pool']
        ]
    ]
];
```

**ThinkPHP8 框架集成:**

```php
// 在 config/service.php 中注册
return [
    'fibers' => \Kode\Fibers\Providers\ThinkPHPService::class
];
```

**原生 PHP 项目:**

```php
// 创建配置文件
$config = require_once 'config/fibers.php';

// 初始化纤程池
$pool = new FiberPool($config['default_pool']);

// 使用纤程池
$results = $pool->concurrent([...]);
```

### ✅ 4. PHP 8.1 原生注解 + IDE 可识别设计

Kode/fibers充分利用PHP 8.1的原生注解功能，提供更好的IDE支持和类型安全：

#### 使用 Attribute 实现元数据标记

```php
use Kode\Fibers\Attributes\FiberSafe;
use Kode\Fibers\Attributes\Timeout;
use Kode\Fibers\Attributes\ChannelListener;

#[FiberSafe] // 表示该方法可在纤程中安全调用
class ApiService 
{
    #[Timeout(10)] // 设置10秒超时
    public function fetchUser(int $id): array
    {
        return json_decode(file_get_contents("https://api.com/users/$id"), true);
    }

    #[ChannelListener('order.created')] // 监听通道事件
    public function onOrderCreated(array $data): void
    {
        // 处理订单创建事件
    }
}
```

#### PHPDoc 辅助 IDE 提示

```php
/**
 * @method static mixed run(callable $task, float $timeout = null)
 * @method static FiberPool pool(array $options = [])
 * @method static Channel channel(string $name, int $buffer = 0)
 * @method static array concurrent(array $tasks, float $timeout = null)
 */
class Fiber {}
```

✅ 在 PhpStorm / VSCode + Intelephense 中均可获得完整补全！

#### 自动类型推断与验证

Kode/fibers通过PHP 8.1的类型系统和注解，可以在开发阶段捕获潜在问题：

```php
#[FiberSafe]
function processUserData(User $user): array {
    // 函数逻辑...
    return ['id' => $user->id, 'name' => $user->name];
}

// IDE会自动提示类型错误
Fiber::run(fn() => processUserData(null)); // 提示：参数1应为User类型
```

### ✅ 5. 通信机制：Channel 与 Event Bus

Kode/fibers提供了强大的纤程间通信机制，包括Channel（类似Go Channel）和Event Bus（发布/订阅模式）：

#### 创建通信通道（类似 Go Channel）

```php
use Kode\Fibers\Channel\Channel;

// 创建带缓冲区的通道（缓冲区大小为10）
$ch = Channel::make('download-results', 10);

// 或者使用辅助函数
$ch = fiber_channel('download-results', 10);

// 生产者：向通道发送数据
Fiber::run(function () use ($ch) {
    foreach ([1, 2, 3] as $i) {
        $ch->push("Data $i");
        usleep(100000); // 模拟异步操作
    }
    $ch->close(); // 关闭通道，防止内存泄漏
});

// 消费者：从通道接收数据
while ($msg = $ch->pop(1)) { // 超时1秒
    echo $msg . "\n";
}

// 非阻塞模式
if ($ch->canPush()) {
    $ch->push('non-blocking data');
}

// 带超时的非阻塞模式
if ($ch->tryPush('data', 0.5)) { // 0.5秒超时
    echo 'Data pushed successfully';
}
```

#### 发布/订阅模型（Event Bus）

```php
use Kode\Fibers\Event\EventBus;
use Kode\Fibers\Event\Event;

// 定义事件类
class PaymentSuccessEvent extends Event {
    public function __construct(public array $paymentData) {
        parent::__construct('payment.success', $paymentData);
    }
}

// 注册事件监听器
EventBus::on('payment.success', function (Event $event) {
    $paymentData = $event->getData();
    // 处理支付成功事件
    notifyAdmin($paymentData);
});

// 注册一次性监听器（只触发一次）
EventBus::once('user.login', fn($event) => logFirstLogin($event->getData()));

// 触发事件
EventBus::fire(new PaymentSuccessEvent([
    'order_id' => 123,
    'amount' => 99.99,
    'user_id' => 456
]));

// 移除事件监听器
EventBus::off('payment.success');

// 带有优先级的事件监听器
EventBus::on('system.shutdown', fn() => saveCriticalData(), 100); // 高优先级
EventBus::on('system.shutdown', fn() => cleanTempFiles(), 50); // 中优先级
EventBus::on('system.shutdown', fn() => logShutdown(), 10); // 低优先级
```

### ✅ 6. 禁用函数检测与环境诊断

Kode/fibers提供了全面的环境检测功能，可以识别潜在的兼容性问题：

#### 检测黑名单函数

```php
use Kode\Fibers\Support\Environment;

// 运行全面的环境诊断
$issues = Environment::diagnose();

// 打印诊断结果
foreach ($issues as $issue) {
    echo "⚠️ {$issue['type']}: {$issue['message']}" . PHP_EOL;
    if (isset($issue['recommendation'])) {
        echo "💡 建议: {$issue['recommendation']}" . PHP_EOL;
    }
}

// 检查特定功能是否可用
if (Environment::hasDisabledFunctions(['exec', 'shell_exec'])) {
    // 提供替代方案
}

// 检查必要的扩展是否安装
if (!Environment::hasRequiredExtensions(['curl', 'mbstring'])) {
    // 提示用户安装扩展
}

// 检查是否可以在析构函数中安全使用Fiber
if (Environment::supportsDestructInFiber()) {
    // PHP 8.4+ 行为
} else {
    // PHP < 8.4 兼容行为
}
```

#### 常见禁用函数影响

| 函数                        | 影响           | 建议                 |
| ------------------------- | ------------ | ------------------ |
| `pcntl_*`                 | 多进程冲突        | 关闭或隔离使用            |
| `set_time_limit`          | 可能中断 suspend | 使用 `@ini_set` 局部关闭 |
| `exec`, `shell_exec`      | 阻塞调用         | 替换为异步执行器           |
| `sleep`, `usleep`         | 阻塞主线程        | 已被 Fiber 内部重写为非阻塞  |
| `exit`, `die`             | 终止整个进程       | 避免使用，改用抛出异常        |
| `header`, `session_start` | 可能破坏上下文      | 在主纤程中使用，或使用上下文管理器  |

> ✅ 我们会在初始化时尝试模拟这些函数的安全替代品。

***

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

// 场景：非阻塞读取文件
$content = Fiber::run(fn() => {
    $handle = fopen('large-file.txt', 'r');
    $content = fread($handle, 1024 * 1024);
    fclose($handle);
    return $content;
});

// 场景：异步等待外部服务响应
$response = Fiber::run(function() {
    $client = new HttpClient();
    return $client->get('https://api.example.com');
}, 5); // 5秒超时
```

✅ 优点：零配置、无副作用\
❌ 缺点：无法复用、无资源管控

***

### 🏗️ 何时使用 `FiberPool`？（生产推荐）

适用于**高并发服务**，如微服务网关、批量任务处理器：

```php
// 场景1：API网关并行请求聚合
$pool = new FiberPool(['size' => 128]);

$userRequests = array_map(fn($userId) => fn() => getUserData($userId), $userIds);
$userData = $pool->concurrent($userRequests, 10);

// 场景2：批量数据处理
$pool = new FiberPool([
    'size' => CpuInfo::getRecommendedPoolSize(4),
    'max_exec_time' => 60,
    'max_retries' => 3
]);

$results = $pool->concurrent(array_map(function($item) {
    return function() use ($item) {
        // 处理单个项目
        return processItem($item);
    };
}, $batchItems));

// 场景3：定时任务执行器
$pool = new FiberPool(['name' => 'scheduler']);

foreach ($tasks as $task) {
    $pool->run(function() use ($task) {
        $delay = $task->getDelay();
        usleep($delay * 1000000); // 非阻塞睡眠
        return $task->execute();
    });
}
```

✅ 支持：

- 资源复用
- 错误重试
- 超时熔断
- 监控埋点
- 并发控制

***

## 🛠️ CLI 命令列表

Kode/fibers提供了一系列命令行工具，方便开发和管理：

```bash
# 初始化配置文件
php vendor/bin/fibers init

# 指定框架类型初始化
php vendor/bin/fibers init --framework=laravel

# 运行环境诊断
php vendor/bin/fibers diagnose

# 查看当前运行的 Fiber ID 和状态
php vendor/bin/fibers status

# 清理僵尸纤程
php vendor/bin/fibers cleanup

# 性能压测（测试最大吞吐）
php vendor/bin/fibers benchmark --concurrency=1000

# 查看帮助信息
php vendor/bin/fibers --help

# 查看特定命令帮助
php vendor/bin/fibers diagnose --help
```

### 常用命令选项

```bash
# 初始化命令选项
--framework=FRAMEWORK    指定框架类型 (laravel, symfony, yii3, thinkphp8, native)
--config=PATH            指定配置文件路径
--quiet, -q              静默模式

# 诊断命令选项
--format=FORMAT          输出格式 (text, json, markdown)
--strict                 严格模式，遇到问题立即退出
--detailed               详细输出

# 基准测试命令选项
--concurrency=N          并发数量
--duration=SECONDS       测试持续时间
--task=TYPE              测试任务类型 (io, cpu, mixed)
```

***

## 🧩 功能实现状态

### ✅ 已实现功能

- [x] **上下文传递机制**：使用 `kode/context` 实现纤程上下文变量传递，支持跨设备
- [x] **分布式 Fiber 调度**：`DistributedScheduler` 支持跨机器任务调度
- [x] **性能监控面板**：`FiberProfiler` 和 `WebUI` 可视化监控（已合并）
- [x] **生态系统集成**：`RuntimeBridge` 支持 Swoole/OpenSwoole/Swow/Workerman 桥接
- [x] **ORM 适配层**：`EloquentAdapter` 和 `FixturesAdapter` 支持 Eloquent、Doctrine 等
- [x] **断路器模式**：`CircuitBreaker` 实现自动熔断和恢复机制
- [x] **负载均衡**：`RoundRobinBalancer` 智能任务分发算法
- [x] **热重载支持**：`HotReloader` 实现不中断服务更新代码
- [x] **可视化管理界面**：`WebUI` 提供 Web UI 管理纤程池和任务
- [x] **连接池支持**：`ConnectionPool` 支持 PDO、Redis 连接池管理
- [x] **协程调试器**：`FiberDebugger` 支持断点、日志、状态监控
- [x] **PHP 8.5 特性支持**：`Php85Features` 自动适配新特性
- [x] **多框架支持**：Laravel、Lumen、Symfony、Hyperf、Webman、Yii3、ThinkPHP8

详细开发计划见 [路线图文档](docs/roadmap.md)。

***

## 📚 参考资料

- [PHP Fibers RFC](https://wiki.php.net/rfc/fibers) - PHP 官方纤程规范
- [PHP 8.1 Fibers 文档](https://www.php.net/manual/en/class.fiber.php) - 官方 API 文档
- [Swoole Coroutine Docs](https://www.swoole.co.uk/docs/modules/swoole-coroutine) - Swoole 协程文档
- [Swow Fiber Guide](https://docs.swow.io/) - Swow 框架纤程指南
- [Go Channels in PHP](https://github.com/amphp/amp) - PHP 中的 Go 通道实现
- [RevoltPHP Event Loop](https://github.com/revoltphp/event-loop) - 事件循环实现参考

***

## 📄 许可证

Apache 2.0 License. See [LICENSE](./LICENSE) for full text.

***

## 🙌 贡献者

欢迎提交 PR！请确保：

- ✅ 单元测试覆盖率 ≥ 90%
- ✅ 符合 PSR-12 编码规范
- ✅ 更新文档与 CHANGELOG

***

> Maintained by **Byte Team - Kode PHP Lab**\
> 🌐 <https://github.com/Kode-php/fibers>

***

✅ **已完成目标清单：**

| 要求                     | 完成情况 |
| ---------------------- | ---- |
| PHP 8.1+ 支持 & 8.4 析构兼容 | ✅    |
| 纤程池 + CPU 感知           | ✅    |
| 多框架配置生成                | ✅    |
| 原生注解 + IDE 识别          | ✅    |
| README 使用说明详尽          | ✅    |
| 禁用函数检测                 | ✅    |
| 通信、队列、IO、超时等           | ✅    |

***

