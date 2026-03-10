# 核心概念

本指南将解释 Kode/Fibers 的核心概念，帮助您更好地理解和使用这个库。

## 什么是 Fiber？

Fiber（纤程）是 PHP 8.1 中引入的一种轻量级线程，允许在单个线程内实现协作式多任务。与传统的多线程不同，Fiber 之间的切换完全由用户代码控制，而不是由操作系统调度。

### Fiber 与线程的区别

| 特性 | Fiber | 线程 |
|------|-------|------|
| 调度 | 用户控制（协作式） | 操作系统控制（抢占式） |
| 上下文切换成本 | 低 | 高 |
| 内存使用 | 低 | 高 |
| 并发模型 | 并发（不是并行） | 并行 |
| 共享内存 | 是 | 是（但需要锁） |

## Kode/Fibers 的核心组件

### 1. FiberPool

FiberPool 是管理 Fiber 实例的核心组件，它负责：

- 创建和复用 Fiber 实例
- 管理 Fiber 的生命周期
- 控制并发执行的 Fiber 数量
- 实现任务超时控制
- 提供任务重试机制

```php
use Kode\Fibers\Core\FiberPool;

$pool = new FiberPool([
    'size' => 16, // 纤程池大小
    'max_exec_time' => 30, // 最大执行时间（秒）
    'max_retries' => 3 // 最大重试次数
]);
```

### 2. Channel

Channel 是 Fiber 之间通信的机制，类似于 Go 语言中的 Channel：

- 支持有缓冲和无缓冲的通信
- 提供阻塞和非阻塞操作
- 支持超时控制
- 可以用于实现生产者-消费者模式

```php
use Kode\Fibers\Channel\Channel;

$channel = Channel::make('data-channel', 10); // 缓冲区大小为10
```

### 3. Task

Task 表示在 Fiber 中执行的工作单元：

- 可以是闭包函数或实现了 Runnable 接口的对象
- 支持超时设置
- 支持优先级
- 支持上下文传递

```php
use Kode\Fibers\Task\Task;

$task = Task::make(fn() => doSomeWork(), ['timeout' => 10]);
```

### 4. TaskRunner

TaskRunner 负责实际执行任务：

- 创建和管理 Fiber 实例
- 处理任务的超时逻辑
- 实现任务重试机制
- 支持优先级任务调度

```php
use Kode\Fibers\Task\TaskRunner;

$result = TaskRunner::run(fn() => doSomeWork(), 10); // 10秒超时
```

### 5. Context

Context 提供了在 Fiber 之间传递上下文数据的机制：

- 每个 Fiber 有自己的上下文
- 支持键值对存储
- 可以在整个调用链中访问

```php
use Kode\Fibers\Context\Context;

// 在一个 Fiber 中设置上下文
Context::set('user_id', 123);

// 在同一个 Fiber 的任何地方获取上下文
$userId = Context::get('user_id');
```

### 6. Environment

Environment 提供了环境检测和诊断功能：

- 检测 PHP 版本兼容性
- 检查禁用函数
- 提供系统信息（如 CPU 核心数）

```php
use Kode\Fibers\Support\Environment;

$issues = Environment::diagnose();
```

## 工作原理

### 1. 纤程池工作流程

1. 用户创建一个 FiberPool 实例，指定池大小和其他配置
2. 用户提交任务到池中
3. 池从可用 Fiber 队列中获取一个 Fiber（或创建一个新的）
4. 将任务分配给 Fiber 执行
5. Fiber 执行完成后，返回结果并返回到可用队列
6. 如果 Fiber 执行超时或失败，根据配置进行重试或报告错误

### 2. 通道通信流程

1. 生产者向通道发送数据
   - 如果通道有缓冲区且未满，数据放入缓冲区并立即返回
   - 如果通道无缓冲区或缓冲区已满，生产者 Fiber 挂起
2. 消费者从通道接收数据
   - 如果通道有数据，立即接收并返回
   - 如果通道为空，消费者 Fiber 挂起
3. 当条件满足时（如通道有数据或有空闲缓冲区），挂起的 Fiber 被唤醒继续执行

## 设计原则

Kode/Fibers 遵循以下设计原则：

1. **轻量级内核 + 插件扩展**：核心功能保持简洁，通过扩展提供更多功能
2. **框架无关**：可以与任何 PHP 框架一起使用
3. **性能优先**：优化纤程的创建、复用和销毁过程
4. **安全可靠**：提供完善的错误处理和资源管理
5. **易用性**：提供简洁的 API，降低使用门槛

## 最佳实践

- 对于生产环境，使用 FiberPool 而不是直接使用 Fiber::run()
- 将纤程池大小设置为 CPU 核心数的 2-4 倍
- 为长时间运行的任务设置合理的超时时间
- 使用 Channel 进行 Fiber 之间的通信，而不是共享状态
- 避免在析构函数中调用 Fiber::suspend()（PHP < 8.4）

## 下一步

- 查看 [Fiber Pools](fiber-pools.md) 文档了解更多关于纤程池的用法
- 查看 [Channels](channels.md) 文档了解更多关于通道通信的用法
- 查看 [框架集成](framework-integration.md) 文档了解如何在不同框架中使用