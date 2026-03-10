# API 参考

本文档详细介绍了 `Kode/fibers` 包的所有公共 API，帮助您快速了解和使用该库。

## 目录

- [核心类](#核心类)
- [通道通信](#通道通信)
- [任务管理](#任务管理)
- [上下文管理](#上下文管理)
- [注解](#注解)
- [辅助函数](#辅助函数)
- [框架集成](#框架集成)

## 核心类

### Fibers 主类

`Kode\Fibers\Fibers` 是整个库的主入口类，提供了所有核心功能的便捷访问。

```php
namespace Kode\Fibers;

class Fibers
{
    /**
     * 运行单个纤程任务
     *
     * @param callable $task 要执行的任务
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 任务的返回值
     * @throws \Exception 如果任务执行失败
     */
    public static function run(callable $task, ?float $timeout = null): mixed;

    /**
     * 创建一个纤程池实例
     *
     * @param array $options 纤程池配置
     * @return \Kode\Fibers\Core\FiberPool 纤程池实例
     */
    public static function pool(array $options = []): \Kode\Fibers\Core\FiberPool;

    /**
     * 创建或获取一个通信通道
     *
     * @param string $name 通道名称
     * @param int $bufferSize 缓冲区大小
     * @return \Kode\Fibers\Channel\Channel 通道实例
     */
    public static function channel(string $name, int $bufferSize = 0): \Kode\Fibers\Channel\Channel;

    /**
     * 并行执行多个任务
     *
     * @param array $tasks 任务数组
     * @param float|null $timeout 整体超时时间（秒）
     * @return array 任务结果数组
     */
    public static function parallel(array $tasks, ?float $timeout = null): array;

    /**
     * 等待所有任务完成
     *
     * @param array $tasks 任务数组
     * @return array 任务结果数组
     */
    public static function waitAll(array $tasks): array;

    /**
     * 带重试逻辑的任务执行
     *
     * @param callable $task 任务函数
     * @param int $maxRetries 最大重试次数
     * @param float $retryDelay 重试延迟（秒）
     * @return mixed 任务结果
     */
    public static function retry(callable $task, int $maxRetries = 3, float $retryDelay = 0.5): mixed;

    /**
     * 纤程安全的睡眠函数
     *
     * @param float $seconds 睡眠秒数
     * @return void
     */
    public static function sleep(float $seconds): void;

    /**
     * 带超时的任务执行
     *
     * @param callable $task 任务函数
     * @param float $timeout 超时时间（秒）
     * @return mixed 任务结果
     * @throws \RuntimeException 如果超时
     */
    public static function withTimeout(callable $task, float $timeout): mixed;

    /**
     * 获取当前纤程ID
     *
     * @return int|string|null 纤程ID，如果不在纤程中则返回null
     */
    public static function getId(): int|string|null;

    /**
     * 检查是否在纤程中
     *
     * @return bool 是否在纤程中
     */
    public static function isInFiber(): bool;

    /**
     * 诊断运行环境
     *
     * @return array 环境问题列表
     */
    public static function diagnose(): array;

    /**
     * 设置应用上下文
     *
     * @param mixed $context 上下文数据
     * @return void
     */
    public static function setAppContext(mixed $context): void;

    /**
     * 获取应用上下文
     *
     * @param mixed $default 默认值
     * @return mixed 上下文数据
     */
    public static function getAppContext(mixed $default = null): mixed;
}
```

### FiberPool 类

`Kode\Fibers\Core\FiberPool` 负责管理纤程池，优化资源使用和性能。

```php
namespace Kode\Fibers\Core;

class FiberPool
{
    /**
     * 构造函数
     *
     * @param array $config 配置数组
     */
    public function __construct(array $config = []);

    /**
     * 在纤程池中运行单个任务
     *
     * @param callable|\Kode\Fibers\Contracts\Runnable $task 任务
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 任务结果
     */
    public function run(callable|\Kode\Fibers\Contracts\Runnable $task, ?float $timeout = null): mixed;

    /**
     * 并行执行多个任务
     *
     * @param array $tasks 任务数组
     * @param float|null $timeout 整体超时时间（秒）
     * @return array 任务结果数组
     */
    public function concurrent(array $tasks, ?float $timeout = null): array;

    /**
     * 获取池配置
     *
     * @return array 配置数组
     */
    public function getConfig(): array;

    /**
     * 获取池大小
     *
     * @return int 池大小
     */
    public function getSize(): int;

    /**
     * 获取活跃纤程数
     *
     * @return int 活跃纤程数
     */
    public function getActiveCount(): int;

    /**
     * 获取已执行的任务总数
     *
     * @return int 任务总数
     */
    public function getTotalTasks(): int;

    /**
     * 获取池名称
     *
     * @return string 池名称
     */
    public function getName(): string;

    /**
     * 获取统计信息
     *
     * @return array 统计信息
     */
    public function getStats(): array;

    /**
     * 重置统计信息
     *
     * @return void
     */
    public function resetStats(): void;

    /**
     * 获取可用纤程数量
     *
     * @return int 可用纤程数量
     */
    public function getAvailableCount(): int;
}
```

## 通道通信

### Channel 类

`Kode\Fibers\Channel\Channel` 提供了纤程间通信的机制。

```php
namespace Kode\Fibers\Channel;

class Channel
{
    /**
     * 构造函数
     *
     * @param string $name 通道名称
     * @param int $bufferSize 缓冲区大小
     */
    public function __construct(string $name = '', int $bufferSize = 0);

    /**
     * 创建或获取一个通道实例
     *
     * @param string $name 通道名称
     * @param int $bufferSize 缓冲区大小
     * @return self 通道实例
     */
    public static function make(string $name, int $bufferSize = 0): self;

    /**
     * 发送消息到通道
     *
     * @param mixed $message 消息内容
     * @param float|null $timeout 超时时间（秒）
     * @return bool 是否发送成功
     * @throws \Kode\Fibers\Exceptions\FiberException 如果在纤程外调用
     * @throws \RuntimeException 如果超时
     */
    public function push(mixed $message, ?float $timeout = null): bool;

    /**
     * 从通道接收消息
     *
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 消息内容
     * @throws \Kode\Fibers\Exceptions\FiberException 如果在纤程外调用
     * @throws \RuntimeException 如果超时
     */
    public function pop(?float $timeout = null): mixed;

    /**
     * 关闭通道
     *
     * @return void
     */
    public function close(): void;

    /**
     * 获取通道名称
     *
     * @return string 通道名称
     */
    public function getName(): string;

    /**
     * 检查通道是否已关闭
     *
     * @return bool 是否已关闭
     */
    public function isClosed(): bool;

    /**
     * 获取通道中消息的数量
     *
     * @return int 消息数量
     */
    public function length(): int;

    /**
     * 获取所有活跃的通道
     *
     * @return array 通道数组
     */
    public static function getActiveChannels(): array;

    /**
     * 创建一个MySQL操作通道
     *
     * @param string $name 通道名称
     * @param string $dsn 数据源名称
     * @param string $username 用户名
     * @param string $password 密码
     * @param array $options PDO选项
     * @return self 通道实例
     */
    public static function mysql(string $name, string $dsn, string $username = '', string $password = '', array $options = []): self;

    /**
     * 创建一个Redis操作通道
     *
     * @param string $name 通道名称
     * @param string $host 主机名
     * @param int $port 端口号
     * @param string $password 密码
     * @param int $database 数据库索引
     * @return self 通道实例
     */
    public static function redis(string $name, string $host = '127.0.0.1', int $port = 6379, string $password = '', int $database = 0): self;

    /**
     * 创建一个HTTP请求通道
     *
     * @param string $name 通道名称
     * @param array $options 请求选项
     * @return self 通道实例
     */
    public static function http(string $name, array $options = []): self;
}
```

## 任务管理

### Task 类

`Kode\Fibers\Task\Task` 表示一个可执行的任务。

```php
namespace Kode\Fibers\Task;

use Kode\Fibers\Contracts\Runnable;

class Task implements Runnable
{
    /**
     * 构造函数
     *
     * @param callable $callback 任务回调函数
     * @param array $context 任务上下文
     */
    public function __construct(callable $callback, array $context = []);

    /**
     * 执行任务
     *
     * @return mixed 任务结果
     */
    public function run(): mixed;

    /**
     * 获取任务ID
     *
     * @return string 任务ID
     */
    public function getId(): string;

    /**
     * 获取任务上下文
     *
     * @return array 任务上下文
     */
    public function getContext(): array;

    /**
     * 设置任务上下文
     *
     * @param array $context 任务上下文
     * @return self 任务实例
     */
    public function withContext(array $context): self;

    /**
     * 获取任务创建时间
     *
     * @return float 创建时间（时间戳）
     */
    public function getCreatedAt(): float;

    /**
     * 获取任务年龄（秒）
     *
     * @return float 任务年龄
     */
    public function getAge(): float;

    /**
     * 创建一个带重试机制的任务
     *
     * @param int $maxRetries 最大重试次数
     * @param float $retryDelay 重试延迟（秒）
     * @return RetryableTask 重试任务实例
     */
    public function withRetry(int $maxRetries = 3, float $retryDelay = 0.5): RetryableTask;

    /**
     * 从可调用对象创建任务
     *
     * @param callable $callback 回调函数
     * @param array $context 任务上下文
     * @return self 任务实例
     */
    public static function make(callable $callback, array $context = []): self;
}
```

### RetryableTask 类

`Kode\Fibers\Task\RetryableTask` 提供了任务重试机制。

```php
namespace Kode\Fibers\Task;

use Kode\Fibers\Attributes\FiberSafe;
use Kode\Fibers\Contracts\Runnable;
use Kode\Fibers\Exceptions\FiberException;

#[FiberSafe]
class RetryableTask implements Runnable
{
    /**
     * 构造函数
     *
     * @param callable $task 任务函数
     * @param int $maxRetries 最大重试次数
     * @param float $retryDelay 重试延迟（秒）
     * @param array $retryOn 重试的异常类型
     * @param array $doNotRetryOn 不重试的异常类型
     * @param callable|null $backoffStrategy 退避策略函数
     */
    public function __construct(
        callable $task,
        int $maxRetries = 3,
        float $retryDelay = 0.5,
        array $retryOn = [],
        array $doNotRetryOn = [],
        ?callable $backoffStrategy = null
    );

    /**
     * 执行任务
     *
     * @return mixed 任务结果
     * @throws \Throwable 如果达到最大重试次数仍失败
     */
    public function run(): mixed;

    /**
     * 判断是否应该重试
     *
     * @param \Throwable $exception 异常
     * @return bool 是否应该重试
     */
    public function shouldRetry(\Throwable $exception): bool;

    /**
     * 设置要重试的异常类型
     *
     * @param string ...$exceptionTypes 异常类型
     * @return self 任务实例
     */
    public function retryOn(string ...$exceptionTypes): self;

    /**
     * 设置不要重试的异常类型
     *
     * @param string ...$exceptionTypes 异常类型
     * @return self 任务实例
     */
    public function doNotRetryOn(string ...$exceptionTypes): self;

    /**
     * 设置退避策略
     *
     * @param callable $strategy 退避策略函数
     * @return self 任务实例
     */
    public function withBackoffStrategy(callable $strategy): self;

    /**
     * 设置指数退避策略
     *
     * @param float $factor 指数因子
     * @return self 任务实例
     */
    public function withExponentialBackoff(float $factor = 2.0): self;

    /**
     * 设置线性退避策略
     *
     * @param float $step 步长
     * @return self 任务实例
     */
    public function withLinearBackoff(float $step = 0.5): self;

    /**
     * 添加随机抖动
     *
     * @param float $jitter 抖动系数
     * @return self 任务实例
     */
    public function withJitter(float $jitter = 0.2): self;

    /**
     * 获取任务ID
     *
     * @return string 任务ID
     */
    public function getId(): string;

    /**
     * 获取任务上下文
     *
     * @return array 任务上下文
     */
    public function getContext(): array;

    /**
     * 从可调用对象创建重试任务
     *
     * @param callable $callback 回调函数
     * @param int $maxRetries 最大重试次数
     * @param float $retryDelay 重试延迟
     * @return self 任务实例
     */
    public static function make(callable $callback, int $maxRetries = 3, float $retryDelay = 0.5): self;
}
```

### TaskRunner 类

`Kode\Fibers\Task\TaskRunner` 负责执行和管理任务。

```php
namespace Kode\Fibers\Task;

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Contracts\Runnable;

class TaskRunner
{
    /**
     * 运行单个任务
     *
     * @param callable|Runnable $task 任务
     * @param float|null $timeout 超时时间
     * @return mixed 任务结果
     */
    public static function run(callable|Runnable $task, ?float $timeout = null): mixed;

    /**
     * 带超时的任务执行
     *
     * @param callable $task 任务
     * @param float $timeout 超时时间
     * @return mixed 任务结果
     * @throws \RuntimeException 如果超时
     */
    public static function runWithTimeout(callable $task, float $timeout): mixed;

    /**
     * 带重试逻辑的任务执行
     *
     * @param callable $task 任务
     * @param int $maxRetries 最大重试次数
     * @param float $retryDelay 重试延迟
     * @return mixed 任务结果
     */
    public static function retry(callable $task, int $maxRetries = 3, float $retryDelay = 0.5): mixed;

    /**
     * 并行执行多个任务
     *
     * @param array $tasks 任务数组
     * @param array $options 选项
     * @return array 任务结果数组
     */
    public static function concurrent(array $tasks, array $options = []): array;

    /**
     * 按优先级执行任务
     *
     * @param array $tasks 任务数组，每个元素是 [任务, 优先级]
     * @param array $options 选项
     * @return array 任务结果数组
     */
    public static function prioritized(array $tasks, array $options = []): array;

    /**
     * 创建可取消的任务
     *
     * @param callable $task 任务
     * @return array [任务函数, 取消函数]
     */
    public static function cancellable(callable $task): array;
}
```

## 上下文管理

### Context 类

`Kode\Fibers\Context\Context` 提供了纤程级别的上下文管理功能。

```php
namespace Kode\Fibers\Context;

use Kode\Context\Context as BaseContext;

class Context extends BaseContext
{
    /**
     * 获取当前纤程的上下文
     *
     * @return static 上下文实例
     */
    public static function current(): static;

    /**
     * 在当前纤程的上下文中设置一个值
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return static 上下文实例
     */
    public static function set(string $key, mixed $value): static;

    /**
     * 从当前纤程的上下文中获取一个值
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed 值
     */
    public static function get(string $key, mixed $default = null): mixed;

    /**
     * 从当前纤程的上下文中删除一个值
     *
     * @param string $key 键名
     * @return bool 是否删除成功
     */
    public static function delete(string $key): bool;

    /**
     * 清除当前纤程的上下文中的所有值
     *
     * @return static 上下文实例
     */
    public static function clear(): static;
}
```

## 注解

### FiberSafe 注解

`Kode\Fibers\Attributes\FiberSafe` 用于标记可在纤程中安全调用的方法或类。

```php
namespace Kode\Fibers\Attributes;

use Attribute;
use Kode\Attributes\BaseFiberSafe;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class FiberSafe extends BaseFiberSafe
{
    // 继承自 BaseFiberSafe
}
```

### Timeout 注解

`Kode\Fibers\Attributes\Timeout` 用于标记方法的超时时间。

```php
namespace Kode\Fibers\Attributes;

use Attribute;
use Kode\Attributes\BaseTimeout;

#[Attribute(Attribute::TARGET_METHOD)]
class Timeout extends BaseTimeout
{
    // 继承自 BaseTimeout
}
```

### ChannelListener 注解

`Kode\Fibers\Attributes\ChannelListener` 用于自动注册通道监听器。

```php
namespace Kode\Fibers\Attributes;

use Attribute;
use Kode\Attributes\BaseChannelListener;

#[Attribute(Attribute::TARGET_METHOD)]
class ChannelListener extends BaseChannelListener
{
    // 继承自 BaseChannelListener
}
```

## 辅助函数

### CpuInfo 类

`Kode\Fibers\Support\CpuInfo` 提供了获取CPU核心数的功能。

```php
namespace Kode\Fibers\Support;

class CpuInfo
{
    /**
     * 获取CPU核心数
     *
     * @return int CPU核心数
     */
    public static function get(): int;
}
```

### Environment 类

`Kode\Fibers\Support\Environment` 提供了环境检测功能。

```php
namespace Kode\Fibers\Support;

class Environment
{
    /**
     * 检查运行环境是否满足要求
     *
     * @return bool 是否满足要求
     */
    public static function check(): bool;

    /**
     * 诊断运行环境问题
     *
     * @return array 环境问题列表
     */
    public static function diagnose(): array;
}
```

## 框架集成

### LaravelServiceProvider 类

```php
namespace Kode\Fibers\Providers;

use Illuminate\Support\ServiceProvider;

class LaravelServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void;

    /**
     * 引导应用程序
     *
     * @return void
     */
    public function boot(): void;
}
```

### SymfonyBundle 类

```php
namespace Kode\Fibers\Providers;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class SymfonyBundle extends Bundle
{
    // 继承自 Bundle
}
```

### Yii3ServiceProvider 类

```php
namespace Kode\Fibers\Providers;

use Yiisoft\Di\Container\ServiceProviderInterface;
use Yiisoft\Di\Container\ContainerInterface;

class Yii3ServiceProvider implements ServiceProviderInterface
{
    /**
     * 注册服务
     *
     * @param ContainerInterface $container 容器实例
     */
    public function register(ContainerInterface $container): void;
}
```

### ThinkPHPService 类

```php
namespace Kode\Fibers\Providers;

class ThinkPHPService
{
    /**
     * 注册服务
     *
     * @return void
     */
    public static function register(): void;

    /**
     * 引导服务
     *
     * @return void
     */
    public static function boot(): void;
}
```

### GenericProvider 类

```php
namespace Kode\Fibers\Providers;

class GenericProvider
{
    /**
     * 初始化服务
     *
     * @param array $config 配置数组
     * @return void
     */
    public static function init(array $config = []): void;
}
```

本文档提供 Kode/Fibers 包的完整 API 参考，包括所有公共类、接口、方法和参数说明。

## 核心类

### Fiber

`Fiber` 是包的主要入口点，提供了快速启动和管理纤程的静态方法。

#### 命名空间

`Kode\Fibers\Facades\Fiber`

#### 主要方法

##### run

在新纤程中执行给定的回调函数并等待结果。

```php
/**
 * 在新纤程中执行任务并等待结果
 *
 * @param callable $task 要执行的任务
 * @param float|null $timeout 超时时间（秒），null表示不超时
 * @return mixed 任务的返回值
 * @throws FiberException 如果任务执行失败
 * @throws TimeoutException 如果任务执行超时
 */
public static function run(callable $task, float $timeout = null): mixed;
```

**示例：**

```php
$result = Fiber::run(fn() => 'Hello, Fiber!');
// $result 现在包含 'Hello, Fiber!'
```

##### pool

创建或获取一个纤程池实例。

```php
/**
 * 创建或获取纤程池实例
 *
 * @param array $options 池配置选项
 * @return FiberPool 纤程池实例
 */
public static function pool(array $options = []): FiberPool;
```

**示例：**

```php
$pool = Fiber::pool(['size' => 8]);
// 使用池执行任务
$results = $pool->concurrent([...]);
```

##### channel

创建或获取一个通道实例。

```php
/**
 * 创建或获取通道实例
 *
 * @param string $name 通道名称
 * @param int $buffer 缓冲区大小，0表示无缓冲
 * @return Channel 通道实例
 */
public static function channel(string $name = 'default', int $buffer = 0): Channel;
```

**示例：**

```php
$channel = Fiber::channel('data-channel', 10);
// 使用通道进行通信
$channel->push('message');
```

##### current

获取当前正在执行的纤程。

```php
/**
 * 获取当前正在执行的纤程
 *
 * @return \Fiber|null 当前纤程，如果不在纤程中则返回null
 */
public static function current(): ?\Fiber;
```

**示例：**

```php
Fiber::run(function() {
    $current = Fiber::current();
    // $current 是当前执行的 Fiber 实例
});
```

##### getId

获取当前纤程的ID。

```php
/**
 * 获取当前纤程的ID
 *
 * @return int 当前纤程的ID，如果不在纤程中则返回-1
 */
public static function getId(): int;
```

**示例：**

```php
Fiber::run(function() {
    $id = Fiber::getId();
    // $id 是当前纤程的唯一标识符
});
```

##### enableSafeDestructMode

启用安全析构模式，解决PHP < 8.4中析构函数不能切换纤程的问题。

```php
/**
 * 启用安全析构模式
 *
 * 在PHP < 8.4中，防止在析构函数中调用Fiber::suspend()导致的致命错误
 */
public static function enableSafeDestructMode(): void;
```

**示例：**

```php
// 通常在应用初始化时调用一次
if (PHP_VERSION_ID < 80400) {
    Fiber::enableSafeDestructMode();
}
```

### FiberPool

`FiberPool` 类用于管理纤程资源，提供了批量执行任务、并发控制和资源监控等功能。

#### 命名空间

`Kode\Fibers\Core\FiberPool`

#### 构造函数

```php
/**
 * 创建纤程池实例
 *
 * @param array $options 池配置选项
 */
public function __construct(array $options = []);
```

**配置选项：**

| 选项 | 类型 | 默认值 | 描述 |
|------|------|--------|------| 
| `size` | int | CPU核心数 * 4 | 纤程池大小 |
| `name` | string | 'default' | 池名称 |
| `max_exec_time` | float | 30 | 任务最大执行时间（秒） |
| `gc_interval` | int | 100 | 垃圾回收间隔（任务数） |
| `onCreate` | callable | null | 纤程创建时的回调 |
| `onDestroy` | callable | null | 纤程销毁时的回调 |

**示例：**

```php
$pool = new FiberPool([
    'size' => 16,
    'name' => 'http-worker',
    'max_exec_time' => 60
]);
```

#### 主要方法

##### execute

在池中执行单个任务。

```php
/**
 * 在池中执行单个任务
 *
 * @param callable $task 要执行的任务
 * @param float|null $timeout 超时时间（秒），null表示使用池默认值
 * @return mixed 任务的返回值
 * @throws FiberException 如果任务执行失败
 * @throws TimeoutException 如果任务执行超时
 */
public function execute(callable $task, float $timeout = null): mixed;
```

**示例：**

```php
$result = $pool->execute(fn() => 'Task result');
// $result 现在包含 'Task result'
```

##### executeTask

执行一个 `Task` 对象。

```php
/**
 * 执行一个Task对象
 *
 * @param Task $task 任务对象
 * @return mixed 任务的返回值
 * @throws FiberException 如果任务执行失败
 * @throws TimeoutException 如果任务执行超时
 */
public function executeTask(Task $task): mixed;
```

**示例：**

```php
$task = Task::make(fn() => 'Task result');
$result = $pool->executeTask($task);
```

##### concurrent

并发执行多个任务。

```php
/**
 * 并发执行多个任务
 *
 * @param array $tasks 任务数组，每个元素是callable或Task对象
 * @param float|null $timeout 总超时时间（秒），null表示不超时
 * @return array 任务结果数组，与输入任务顺序一致
 */
public function concurrent(array $tasks, float $timeout = null): array;
```

**示例：**

```php
$results = $pool->concurrent([
    fn() => 'Result 1',
    fn() => 'Result 2',
    fn() => 'Result 3'
]);
// $results 现在包含 ['Result 1', 'Result 2', 'Result 3']
```

##### resize

调整纤程池大小。

```php
/**
 * 调整纤程池大小
 *
 * @param int $size 新的池大小
 * @return void
 */
public function resize(int $size): void;
```

**示例：**

```php
// 将池大小从16增加到32
$pool->resize(32);
```

##### getSize

获取当前纤程池大小。

```php
/**
 * 获取当前纤程池大小
 *
 * @return int 池大小
 */
public function getSize(): int;
```

**示例：**

```php
$size = $pool->getSize();
// $size 是当前池的大小
```

##### stats

获取纤程池统计信息。

```php
/**
 * 获取纤程池统计信息
 *
 * @return array 统计信息数组
 */
public function stats(): array;
```

**返回值结构：**

```php
[
    'active_fibers' => int,      // 当前活动的纤程数
    'pending_tasks' => int,      // 等待执行的任务数
    'completed_tasks' => int,    // 已完成的任务总数
    'failed_tasks' => int,       // 失败的任务总数
    'average_execution_time' => float, // 平均执行时间（毫秒）
    'peak_active_fibers' => int  // 峰值活动纤程数
]
```

**示例：**

```php
$stats = $pool->stats();
// 输出：[active_fibers => 5, pending_tasks => 0, ...]
```

##### shutdown

关闭纤程池，释放所有资源。

```php
/**
 * 关闭纤程池
 *
 * @param bool $wait 是否等待所有任务完成
 * @return void
 */
public function shutdown(bool $wait = true): void;
```

**示例：**

```php
// 等待所有任务完成后关闭
$pool->shutdown(true);
```

### Channel

`Channel` 类提供了纤程间通信的机制，类似于 Go 语言中的通道。

#### 命名空间

`Kode\Fibers\Channel\Channel`

#### 静态方法

##### make

创建或获取一个通道实例。

```php
/**
 * 创建或获取通道实例
 *
 * @param string $name 通道名称
 * @param int $buffer 缓冲区大小，0表示无缓冲
 * @return Channel 通道实例
 */
public static function make(string $name = 'default', int $buffer = 0): Channel;
```

**示例：**

```php
$channel = Channel::make('message-queue', 100);
```

#### 构造函数

```php
/**
 * 创建通道实例
 *
 * @param int $buffer 缓冲区大小，0表示无缓冲
 */
public function __construct(int $buffer = 0);
```

**示例：**

```php
// 创建无缓冲通道
$channel = new Channel();

// 创建缓冲大小为10的通道
$channel = new Channel(10);
```

#### 主要方法

##### push

向通道发送数据。

```php
/**
 * 向通道发送数据
 *
 * @param mixed $data 要发送的数据
 * @param float|null $timeout 超时时间（秒），null表示不超时
 * @return bool 是否成功发送
 * @throws ChannelClosedException 如果通道已关闭
 */
public function push(mixed $data, float $timeout = null): bool;
```

**示例：**

```php
// 发送数据，最多等待1秒
$success = $channel->push('hello', 1.0);
if ($success) {
    echo "Data sent successfully\n";
}
```

##### pop

从通道接收数据。

```php
/**
 * 从通道接收数据
 *
 * @param float|null $timeout 超时时间（秒），null表示不超时
 * @return mixed 接收到的数据，如果超时则返回null
 * @throws ChannelClosedException 如果通道已关闭且为空
 */
public function pop(float $timeout = null): mixed;
```

**示例：**

```php
// 接收数据，最多等待1秒
$data = $channel->pop(1.0);
if ($data !== null) {
    echo "Received: $data\n";
} else {
    echo "Timeout waiting for data\n";
}
```

##### tryPush

尝试非阻塞地向通道发送数据。

```php
/**
 * 尝试非阻塞地向通道发送数据
 *
 * @param mixed $data 要发送的数据
 * @return bool 是否成功发送
 * @throws ChannelClosedException 如果通道已关闭
 */
public function tryPush(mixed $data): bool;
```

**示例：**

```php
// 尝试发送数据，但不会阻塞
if ($channel->tryPush('hello')) {
    echo "Data sent immediately\n";
} else {
    echo "Could not send data immediately\n";
}
```

##### tryPop

尝试非阻塞地从通道接收数据。

```php
/**
 * 尝试非阻塞地从通道接收数据
 *
 * @return mixed|null 接收到的数据，如果没有数据则返回null
 * @throws ChannelClosedException 如果通道已关闭且为空
 */
public function tryPop(): mixed;
```

**示例：**

```php
// 尝试接收数据，但不会阻塞
$data = $channel->tryPop();
if ($data !== null) {
    echo "Received: $data immediately\n";
} else {
    echo "No data available\n";
}
```

##### close

关闭通道。

```php
/**
 * 关闭通道
 *
 * @return void
 */
public function close(): void;
```

**示例：**

```php
// 关闭通道，不再接受新的发送操作
$channel->close();
```

##### isClosed

检查通道是否已关闭。

```php
/**
 * 检查通道是否已关闭
 *
 * @return bool 是否已关闭
 */
public function isClosed(): bool;
```

**示例：**

```php
if ($channel->isClosed()) {
    echo "Channel is closed\n";
} else {
    echo "Channel is open\n";
}
```

##### size

获取通道中的数据量。

```php
/**
 * 获取通道中的数据量
 *
 * @return int 数据量
 */
public function size(): int;
```

**示例：**

```php
$count = $channel->size();
echo "There are $count items in the channel\n";
```

##### capacity

获取通道的缓冲区容量。

```php
/**
 * 获取通道的缓冲区容量
 *
 * @return int 容量
 */
public function capacity(): int;
```

**示例：**

```php
$capacity = $channel->capacity();
echo "Channel capacity is $capacity\n";
```

## 任务相关类

### Task

`Task` 类表示一个可在纤程中执行的任务，提供了超时控制、重试机制等功能。

#### 命名空间

`Kode\Fibers\Task\Task`

#### 静态方法

##### make

创建一个新的任务实例。

```php
/**
 * 创建任务实例
 *
 * @param callable $callback 任务回调函数
 * @return Task 任务实例
 */
public static function make(callable $callback): Task;
```

**示例：**

```php
$task = Task::make(fn() => 'Task result');
```

#### 主要方法

##### withTimeout

设置任务超时时间。

```php
/**
 * 设置任务超时时间
 *
 * @param float $timeout 超时时间（秒）
 * @return Task 当前任务实例，用于链式调用
 */
public function withTimeout(float $timeout): Task;
```

**示例：**

```php
$task = Task::make(fn() => longRunningOperation())->withTimeout(10.0);
```

##### withRetry

设置任务重试策略。

```php
/**
 * 设置任务重试策略
 *
 * @param int $times 重试次数
 * @param callable|null $condition 重试条件回调，返回true时才重试
 * @param callable|null $backoff 退避策略回调，返回下次重试等待时间（秒）
 * @return Task 当前任务实例，用于链式调用
 */
public function withRetry(int $times, ?callable $condition = null, ?callable $backoff = null): Task;
```

**示例：**

```php
$task = Task::make(fn() => unstableOperation())
    ->withRetry(3, function($error) {
        // 只对网络异常重试
        return $error instanceof NetworkException;
    }, function($attempt) {
        // 指数退避：1, 2, 4秒
        return min(10, pow(2, $attempt - 1));
    });
```

##### withContext

设置任务上下文数据。

```php
/**
 * 设置任务上下文数据
 *
 * @param array $context 上下文数据
 * @return Task 当前任务实例，用于链式调用
 */
public function withContext(array $context): Task;
```

**示例：**

```php
$task = Task::make(fn() => processWithContext())
    ->withContext(['request_id' => 'req-12345']);
```

##### run

执行任务。

```php
/**
 * 执行任务
 *
 * @return mixed 任务的返回值
 * @throws Exception 如果任务执行失败且重试次数已用完
 */
public function run(): mixed;
```

**示例：**

```php
try {
    $result = $task->run();
    echo "Task completed with result: $result\n";
} catch (\Exception $e) {
    echo "Task failed: ", $e->getMessage(), "\n";
}
```

### RetryableTask

`RetryableTask` 是一个预配置了重试策略的任务类。

#### 命名空间

`Kode\Fibers\Task\RetryableTask`

#### 静态方法

##### make

创建一个带重试功能的任务实例。

```php
/**
 * 创建带重试功能的任务实例
 *
 * @param callable $callback 任务回调函数
 * @param int $maxRetries 最大重试次数
 * @param callable|null $condition 重试条件回调
 * @param callable|null $backoff 退避策略回调
 * @return RetryableTask 任务实例
 */
public static function make(callable $callback, int $maxRetries = 3, ?callable $condition = null, ?callable $backoff = null): RetryableTask;
```

**示例：**

```php
$task = RetryableTask::make(
    fn() => apiCall(),
    3, // 最多重试3次
    fn($error) => $error instanceof ApiException && $error->isRetryable(),
    fn($attempt) => $attempt * 1.5 // 线性退避
);
```

## 上下文管理

### Context

`Context` 类提供了在纤程之间传递上下文数据的机制。

#### 命名空间

`Kode\Fibers\Context\Context`

#### 主要方法

##### set

设置上下文值。

```php
/**
 * 设置上下文值
 *
 * @param string $key 键名
 * @param mixed $value 值
 * @return void
 */
public static function set(string $key, mixed $value): void;
```

**示例：**

```php
Context::set('request_id', 'req-12345');
```

##### get

获取上下文值。

```php
/**
 * 获取上下文值
 *
 * @param string $key 键名
 * @param mixed $default 默认值
 * @return mixed 上下文值，如果不存在则返回默认值
 */
public static function get(string $key, mixed $default = null): mixed;
```

**示例：**

```php
$requestId = Context::get('request_id', 'unknown');
echo "Processing request: $requestId\n";
```

##### has

检查上下文是否包含指定键。

```php
/**
 * 检查上下文是否包含指定键
 *
 * @param string $key 键名
 * @return bool 是否包含
 */
public static function has(string $key): bool;
```

**示例：**

```php
if (Context::has('user_id')) {
    echo "User is authenticated\n";
}
```

##### delete

删除上下文值。

```php
/**
 * 删除上下文值
 *
 * @param string $key 键名
 * @return void
 */
public static function delete(string $key): void;
```

**示例：**

```php
Context::delete('temporary_data');
```

##### clear

清除所有上下文值。

```php
/**
 * 清除所有上下文值
 *
 * @return void
 */
public static function clear(): void;
```

**示例：**

```php
// 在任务完成后清除上下文
Context::clear();
```

##### all

获取所有上下文值。

```php
/**
 * 获取所有上下文值
 *
 * @return array 上下文值数组
 */
public static function all(): array;
```

**示例：**

```php
$context = Context::all();
// $context 包含所有当前设置的上下文值
```

## 环境检测

### Environment

`Environment` 类提供了环境检测和诊断功能。

#### 命名空间

`Kode\Fibers\Support\Environment`

#### 主要方法

##### diagnose

诊断当前环境，检查PHP版本、禁用函数等。

```php
/**
 * 诊断当前环境
 *
 * @return array 诊断结果数组
 */
public static function diagnose(): array;
```

**返回值结构：**

```php
[
    [
        'type' => string,  // 问题类型（'version', 'function_disabled', 'fiber_unsafe'等）
        'severity' => string, // 严重性（'error', 'warning', 'info'）
        'message' => string,  // 问题描述
        'solution' => string  // 建议解决方案
    ],
    // ...更多问题
]
```

**示例：**

```php
$issues = Environment::diagnose();
foreach ($issues as $issue) {
    echo "{$issue['severity']}: {$issue['message']}\n";
}
```

##### getPhpVersion

获取当前PHP版本信息。

```php
/**
 * 获取当前PHP版本信息
 *
 * @return array PHP版本信息数组
 */
public static function getPhpVersion(): array;
```

**返回值结构：**

```php
[
    'full' => string,    // 完整版本号，如 '8.1.10'
    'major' => int,      // 主版本号，如 8
    'minor' => int,      // 次版本号，如 1
    'patch' => int,      // 补丁版本号，如 10
    'isSupported' => bool // 是否支持（>= 8.1）
]
```

**示例：**

```php
$version = Environment::getPhpVersion();
echo "Running PHP {$version['full']}\n";
if (!$version['isSupported']) {
    echo "Warning: PHP version is not supported!\n";
}
```

##### isFunctionDisabled

检查指定函数是否被禁用。

```php
/**
 * 检查指定函数是否被禁用
 *
 * @param string $function 函数名
 * @return bool 是否被禁用
 */
public static function isFunctionDisabled(string $function): bool;
```

**示例：**

```php
if (Environment::isFunctionDisabled('proc_open')) {
    echo "Warning: proc_open is disabled\n";
}
```

##### getDisabledFunctions

获取所有被禁用的函数。

```php
/**
 * 获取所有被禁用的函数
 *
 * @return array 被禁用的函数数组
 */
public static function getDisabledFunctions(): array;
```

**示例：**

```php
$disabled = Environment::getDisabledFunctions();
echo "Disabled functions: ", implode(', ', $disabled), "\n";
```

## CPU 信息

### CpuInfo

`CpuInfo` 类提供了获取 CPU 相关信息的功能。

#### 命名空间

`Kode\Fibers\Support\CpuInfo`

#### 主要方法

##### get

获取 CPU 核心数。

```php
/**
 * 获取CPU核心数
 *
 * @return int CPU核心数
 */
public static function get(): int;
```

**示例：**

```php
$cpuCount = CpuInfo::get();
echo "CPU has $cpuCount cores\n";

// 使用CPU核心数设置合理的池大小
$pool = new FiberPool(['size' => $cpuCount * 4]);
```

## 工具类

### ConnectionPool

`ConnectionPool` 类提供了数据库连接池功能。

#### 命名空间

`Kode\Fibers\Support\ConnectionPool`

#### 构造函数

```php
/**
 * 创建连接池实例
 *
 * @param callable $creator 连接创建函数
 * @param int $maxSize 最大连接数
 * @param callable|null $validator 连接验证函数
 * @param callable|null $destroyer 连接销毁函数
 */
public function __construct(callable $creator, int $maxSize = 10, ?callable $validator = null, ?callable $destroyer = null);
```

**示例：**

```php
$dbPool = new ConnectionPool(
    function() {
        return new PDO(
            'mysql:host=localhost;dbname=test',
            'username',
            'password'
        );
    },
    20 // 最大20个连接
);
```

#### 主要方法

##### acquire

获取一个连接。

```php
/**
 * 获取一个连接
 *
 * @param float|null $timeout 超时时间（秒）
 * @return mixed 连接对象
 * @throws PoolException 如果无法获取连接
 */
public function acquire(float $timeout = null): mixed;
```

**示例：**

```php
// 获取一个数据库连接，最多等待5秒
$db = $dbPool->acquire(5.0);
try {
    // 使用连接
    $stmt = $db->query('SELECT * FROM users');
    $users = $stmt->fetchAll();
} finally {
    // 释放连接回池
    $dbPool->release($db);
}
```

##### release

释放一个连接回池。

```php
/**
 * 释放一个连接回池
 *
 * @param mixed $connection 连接对象
 * @return void
 */
public function release(mixed $connection): void;
```

**示例：**

```php
// 释放连接回池
$dbPool->release($db);
```

##### close

关闭连接池，释放所有资源。

```php
/**
 * 关闭连接池
 *
 * @return void
 */
public function close(): void;
```

**示例：**

```php
// 在应用关闭时关闭连接池
$dbPool->close();
```

## 异常类

### FiberException

基础纤程异常类。

```php
class FiberException extends \Exception {}
```

### TimeoutException

任务执行超时时抛出。

```php
class TimeoutException extends FiberException {}
```

### PoolException

纤程池相关异常。

```php
class PoolException extends FiberException {}
```

### ChannelException

通道相关异常的基类。

```php
class ChannelException extends FiberException {}
```

### ChannelClosedException

通道已关闭时抛出。

```php
class ChannelClosedException extends ChannelException {}
```

### TaskException

任务执行相关异常。

```php
class TaskException extends FiberException {}
```

## 注解

### Timeout

用于设置方法的执行超时时间。

```php
use Kode\Attributes\Timeout;

#[Timeout(10)] // 10秒超时
public function fetchData() {
    // 可能耗时的操作
}
```

### ChannelListener

用于标记通道监听器方法。

```php
use Kode\Attributes\ChannelListener;

#[ChannelListener('data-updated')] // 监听名为'data-updated'的通道
public function onDataUpdated($data) {
    // 处理更新的数据
}
```

## 接口

### Runnable

可运行的任务接口。

```php
interface Runnable {
    /**
     * 运行任务
     *
     * @return mixed 任务结果
     */
    public function run(): mixed;
}
```

### TaskScheduler

任务调度器接口。

```php
interface TaskScheduler {
    /**
     * 调度任务
     *
     * @param callable|Runnable $task 任务
     * @param float|null $timeout 超时时间
     * @return mixed 任务结果
     */
    public function schedule(callable|Runnable $task, float $timeout = null): mixed;
}
```

### ChannelManager

通道管理器接口。

```php
interface ChannelManager {
    /**
     * 创建通道
     *
     * @param string $name 通道名称
     * @param int $buffer 缓冲区大小
     * @return Channel 通道实例
     */
    public function create(string $name, int $buffer = 0): Channel;
    
    /**
     * 获取通道
     *
     * @param string $name 通道名称
     * @return Channel 通道实例
     * @throws ChannelException 如果通道不存在
     */
    public function get(string $name): Channel;
    
    /**
     * 检查通道是否存在
     *
     * @param string $name 通道名称
     * @return bool 是否存在
     */
    public function has(string $name): bool;
    
    /**
     * 删除通道
     *
     * @param string $name 通道名称
     * @return void
     */
    public function delete(string $name): void;
    
    /**
     * 获取所有通道名称
     *
     * @return array 通道名称数组
     */
    public function all(): array;
}
```

## 扩展接口

### EventLoopInterface

事件循环接口。

```php
interface EventLoopInterface {
    /**
     * 运行事件循环
     *
     * @return void
     */
    public function run(): void;
    
    /**
     * 停止事件循环
     *
     * @return void
     */
    public function stop(): void;
    
    /**
     * 添加定时器
     *
     * @param float $delay 延迟时间（秒）
     * @param callable $callback 回调函数
     * @return string 定时器ID
     */
    public function setTimeout(float $delay, callable $callback): string;
    
    /**
     * 取消定时器
     *
     * @param string $id 定时器ID
     * @return void
     */
    public function clearTimeout(string $id): void;
    
    /**
     * 添加周期性定时器
     *
     * @param float $interval 间隔时间（秒）
     * @param callable $callback 回调函数
     * @return string 定时器ID
     */
    public function setInterval(float $interval, callable $callback): string;
    
    /**
     * 取消周期性定时器
     *
     * @param string $id 定时器ID
     * @return void
     */
    public function clearInterval(string $id): void;
}
```

### HttpClientInterface

HTTP 客户端接口。

```php
interface HttpClientInterface {
    /**
     * 发送GET请求
     *
     * @param string $url URL
     * @param array $options 请求选项
     * @return HttpResponse 响应对象
     */
    public function get(string $url, array $options = []): HttpResponse;
    
    /**
     * 发送POST请求
     *
     * @param string $url URL
     * @param mixed $body 请求体
     * @param array $options 请求选项
     * @return HttpResponse 响应对象
     */
    public function post(string $url, mixed $body = null, array $options = []): HttpResponse;
    
    /**
     * 发送请求
     *
     * @param string $method HTTP方法
     * @param string $url URL
     * @param array $options 请求选项
     * @return HttpResponse 响应对象
     */
    public function request(string $method, string $url, array $options = []): HttpResponse;
}
```

## 命令行工具

`Kode/fibers` 包提供了一组命令行工具，用于初始化配置、查看状态等。

### 命令列表

#### init

初始化配置文件。

```bash
php vendor/bin/fibers init
```

**选项：**

- `--framework`：指定框架类型（laravel, symfony, yii3, thinkphp8, plain）
- `--force`：强制覆盖现有配置

#### status

查看当前运行的 Fiber 状态。

```bash
php vendor/bin/fibers status
```

**选项：**

- `--json`：以JSON格式输出状态信息

#### cleanup

清理僵尸纤程和资源。

```bash
php vendor/bin/fibers cleanup
```

**选项：**

- `--force`：强制清理所有资源

#### benchmark

性能压测。

```bash
php vendor/bin/fibers benchmark --concurrency=1000
```

**选项：**

- `--concurrency`：并发数量
- `--duration`：测试持续时间（秒）
- `--task-type`：任务类型（io, cpu, mixed）

#### diagnose

诊断环境问题。

```bash
php vendor/bin/fibers diagnose
```

**选项：**

- `--detail`：显示详细诊断信息
- `--json`：以JSON格式输出诊断结果

## 配置参考

### 默认配置结构

```php
return [
    // 默认纤程池配置
    'default_pool' => [
        'size' => env('FIBER_POOL_SIZE', CpuInfo::get() * 4),
        'max_exec_time' => 30,
        'gc_interval' => 100,
        'strict_destruct_check' => true,
        'enable_monitoring' => false,
    ],
    
    // 通道配置
    'channels' => [
        'default' => ['buffer_size' => 0],
        // 自定义通道配置
        // 'orders' => ['buffer_size' => 100],
    ],
    
    // 功能配置
    'features' => [
        'auto_suspend_io' => true,
        'enable_context' => true,
        'enable_event_bus' => false,
    ],
    
    // 框架集成配置
    'framework' => [
        'type' => env('FIBER_FRAMEWORK', 'plain'),
        'middleware' => true,
        'service_provider' => true,
    ],
    
    // 监控配置
    'monitoring' => [
        'enabled' => env('FIBER_MONITORING', false),
        'metrics_prefix' => 'fibers_',
        'statsd' => [
            'host' => 'localhost',
            'port' => 8125,
        ],
    ],
];
```

## 框架特定配置

### Laravel 配置

在 Laravel 中，可以在 `config/fibers.php` 文件中配置 Kode/fibers。

**服务提供者：** `Kode\Fibers\Integrations\Laravel\FibersServiceProvider`

**中间件：** `Kode\Fibers\Integrations\Laravel\Middleware\EnableFibers`

### Symfony 配置

在 Symfony 中，可以在 `config/packages/fibers.yaml` 文件中配置 Kode/fibers。

```yaml
kode_fibers:
    default_pool:
        size: '%env(int:FIBER_POOL_SIZE)%'
        max_exec_time: 30
    channels:
        default:
            buffer_size: 0
    features:
        auto_suspend_io: true
```

**捆绑包：** `Kode\Fibers\Integrations\Symfony\FibersBundle`

### Yii3 配置

在 Yii3 中，可以在 `config/common.php` 文件中配置 Kode/fibers。

```php
return [
    'components' => [
        'fibers' => [
            'class' => \Kode\Fibers\Integrations\Yii\FibersManager::class,
            'defaultPool' => [
                'size' => env('FIBER_POOL_SIZE', CpuInfo::get() * 4),
                'max_exec_time' => 30,
            ],
        ],
    ],
];
```

### ThinkPHP8 配置

在 ThinkPHP8 中，可以在 `config/fibers.php` 文件中配置 Kode/fibers。

**服务：** `\Kode\Fibers\Integrations\ThinkPHP\Service`

## 版本兼容性

| PHP 版本 | 支持情况 | 注意事项 |
|---------|---------|---------|
| 8.1.x | ✅ 完全支持 | 需要额外注意析构函数中的纤程切换 |
| 8.2.x | ✅ 完全支持 | 需要额外注意析构函数中的纤程切换 |
| 8.3.x | ✅ 完全支持 | 需要额外注意析构函数中的纤程切换 |
| 8.4.x+ | ✅ 完全支持 | 析构函数中的纤程切换已被允许 |
| < 8.1 | ❌ 不支持 | 请升级到 PHP 8.1 或更高版本 |

## 扩展集成

### Swoole 集成

Kode/fibers 可以与 Swoole 扩展集成，利用 Swoole 的异步 I/O 能力。

**适配器：** `Kode\Fibers\Adapters\SwooleAdapter`

```php
// 启用 Swoole 适配器
Fiber::setAdapter(new \Kode\Fibers\Adapters\SwooleAdapter());
```

### Swow 集成

Kode/fibers 可以与 Swow 扩展集成。

**适配器：** `Kode\Fibers\Adapters\SwowAdapter`

```php
// 启用 Swow 适配器
Fiber::setAdapter(new \Kode\Fibers\Adapters\SwowAdapter());
```

### Amp 集成

Kode/fibers 可以与 Amp 库集成。

**适配器：** `Kode\Fibers\Adapters\AmpAdapter`

```php
// 启用 Amp 适配器
Fiber::setAdapter(new \Kode\Fibers\Adapters\AmpAdapter());
```

## 常见问题解答

### 1. 为什么我的任务总是超时？

- 检查任务是否执行了长时间阻塞操作
- 检查网络连接或数据库查询是否正常
- 尝试增加任务的超时时间
- 考虑将大型任务拆分为多个小型任务

### 2. 如何处理纤程中的异常？

- 使用 try/catch 捕获纤程内的异常
- 对于 `Fiber::run()`，捕获返回的异常
- 对于 `FiberPool::execute()` 和 `FiberPool::concurrent()`，异常会被传播到调用者
- 考虑使用 `Task::withRetry()` 自动重试失败的任务

### 3. 如何优化纤程池性能？

- 根据应用类型和硬件资源设置合理的池大小
- 复用纤程池实例而非频繁创建新实例
- 批量提交任务而非逐个提交
- 监控池性能指标并根据需要调整配置
- 结合使用异步 I/O 扩展（如 Swoole、Swow）