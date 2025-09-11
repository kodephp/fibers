写一个字节团队的trae IDE识别的md文档以便模型生成：
要开发一个 健壮全面的 Fiber 纤程客户端 的composer的包，命名 nova/fibers，需要封装开发时全面并便捷方法使用，命令方法尽量简洁好记，请帮忙补充完善，更健壮的架构代码实现：
1、php8.1+使用，php8.1 和 php8.4的区别：注意: 在 PHP 8.4.0 之前，不允许在对象析构方法执行期间切换纤程。判断php版本是否支持，不支持则自动降级处理。
2、纤程池，一键纤程，其他类/方法可方便加入纤程，暂停，查看id，纤程间通信，队列，redis，MySQL，pgsql，HTTP，超时控制等操作（参考swow/swoole等协程框架使用）。如线程池设置为 CPU 核心数的 2-4 倍，增加获取CPU数量方法可在配置文件中使用。请帮忙补充完善。
3、需要开发所有框架通用的包，主要偏向laravel、symfony等框架，但是也要适配yii3、thinkphp8或自建框架等。生成框架推送配置文件的方法，便于对应框架使用命令直接生成配置文件。如没有则使用自带的命令生成相关配置文件。
4、是否使用php8原生注解特性，IDE可识别的方式？
5、README.md的详细使用方法。如线程池：什么情况下使用，如何使用；一键协程：是否尽量在不改变在运行的生产环境中的使用，如何使用等等，帮忙补充完成。
6、如有禁用函数使用，增加检测环境方法，并在md文档中说明。


以下是为 **`nova/fibers`** Composer 包设计的完整 `README.md` 文档，专为字节团队开发健壮、全面、IDE 可识别的 **Fiber 纤程客户端库** 而编写。该文档遵循现代 PHP 8.1+ 最佳实践，兼容 Laravel、Symfony、Yii3、ThinkPHP8 及自建框架，并支持 IDE 智能提示（通过 PHPDoc + 原生注解），包含自动配置生成、版本兼容性处理、池化管理、通信机制等核心功能。如使用 协变/逆变、反射等，可实现更安全、健壮性高、速度快 则直接使用。帮忙完善相关功能。

---

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

本包采用 **“轻量内核 + 插件扩展”** 设计：

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
```

---

## 🧩 扩展建议（未来路线图）

- [ ] Fiber 上下文变量传递（类似 Context）
- [ ] 分布式 Fiber 调度（跨机器）
- [ ] Fiber Profiler 可视化面板
- [ ] 与 Swoole/OpenSwoole/Swow/Workerman 无缝桥接
- [ ] Fiber-aware ORM（Eloquent/Fixtures）

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

> Maintained by **Byte Team - Nova PHP Lab**  
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
