# 测试指南

## 概述

本指南介绍了如何为 nova/fibers 包编写和运行测试。测试是确保代码质量和功能正确性的关键部分。我们使用 PHPUnit 作为测试框架，并遵循 PSR-12 编码规范。

## 测试结构

测试文件位于 [tests](file:///d:/wwwroot/composer/fibers/tests/) 目录中，与 [src](file:///d:/wwwroot/composer/fibers/src/) 目录中的源代码结构相对应：

```
tests/
├── ChannelTest.php
├── EventBusTest.php
├── EnvironmentTest.php
├── EventLoopTest.php
├── FiberPoolTest.php
├── LocalSchedulerTest.php
├── SchedulerTest.php
├── AdvancedFeaturesTest.php
└── Commands/
    └── RunExampleCommandTest.php
```

## 编写测试

### 基本测试结构

每个测试类应该继承 `PHPUnit\Framework\TestCase` 并遵循以下结构：

```php
<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Support\Environment;

/**
 * 测试类描述
 *
 * @package Nova\Fibers\Tests
 */
class ExampleTest extends TestCase
{
    /**
     * 测试方法描述
     *
     * @covers \Nova\Fibers\ExampleClass::exampleMethod
     * @return void
     */
    public function testExample(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        // 测试代码
        $this->assertTrue(true);
    }
}
```

### Fiber 环境检查

由于 nova/fibers 包依赖于 PHP Fiber 功能，所有测试都应该检查当前环境是否支持 Fiber：

```php
if (!Environment::checkFiberSupport()) {
    $this->markTestSkipped('Fiber support is not available in this environment.');
}
```

### 测试 EventLoop 功能

测试 EventLoop 功能时，通常需要直接调用私有方法来触发事件处理：

```php
// 获取 EventLoop 实例
$eventLoop = EventLoop::getInstance();

// 使用反射调用私有方法
$reflection = new \ReflectionClass($eventLoop);
$method = $reflection->getMethod('processDeferQueue');
$method->setAccessible(true);
$method->invoke($eventLoop);
```

### 测试异步功能

对于异步功能的测试，可以使用 usleep 来等待异步操作完成：

```php
public function testDelay(): void
{
    if (!Environment::checkFiberSupport()) {
        $this->markTestSkipped('Fiber support is not available in this environment.');
    }

    $result = [];
    
    // 添加一个延迟任务
    $timerId = EventLoop::delay(0.01, function() use (&$result) {
        $result[] = 'delayed';
    });
    
    $result[] = 'immediate';
    
    // 等待足够时间让定时器到期
    usleep(15000); // 15ms
    
    // 运行一次tick处理定时器队列
    $eventLoop = EventLoop::getInstance();
    $reflection = new \ReflectionClass($eventLoop);
    $method = $reflection->getMethod('processTimers');
    $method->setAccessible(true);
    $method->invoke($eventLoop);
    
    $this->assertEquals(['immediate', 'delayed'], $result);
}
```

## 运行测试

### 运行所有测试

```bash
./vendor/bin/phpunit
```

### 运行特定测试类

```bash
./vendor/bin/phpunit tests/EventLoopTest.php
```

### 运行特定测试方法

```bash
./vendor/bin/phpunit --filter testDefer tests/EventLoopTest.php
```

### 生成代码覆盖率报告

```bash
./vendor/bin/phpunit --coverage-html coverage
```

## 测试用例详解

### EventLoopTest

[EventLoopTest.php](file:///d:/wwwroot/composer/fibers/tests/EventLoopTest.php) 测试 EventLoop 类的所有功能：

1. **testDefer** - 测试 defer 功能
2. **testDelay** - 测试 delay 功能和定时器取消
3. **testRepeat** - 测试 repeat 功能和重复定时器取消
4. **testOnReadable** - 测试流可读事件
5. **testOnWritable** - 测试流可写事件
6. **testOnSignal** - 测试信号处理

### SchedulerTest

[SchedulerTest.php](file:///d:/wwwroot/composer/fibers/tests/SchedulerTest.php) 测试 Scheduler 类的基本功能：

1. **testCreateScheduler** - 测试调度器创建
2. **testAddTask** - 测试添加任务
3. **testGetTaskQueue** - 测试获取任务队列
4. **testGetActiveFiberCount** - 测试获取活跃纤程数量

### LocalSchedulerTest

[LocalSchedulerTest.php](file:///d:/wwwroot/composer/fibers/tests/LocalSchedulerTest.php) 测试 LocalScheduler 类的功能：

1. **testCreateLocalScheduler** - 测试本地调度器创建
2. **testSubmit** - 测试提交任务
3. **testGetStatus** - 测试获取任务状态
4. **testCancel** - 测试取消任务
5. **testGetClusterInfo** - 测试获取集群信息

## 最佳实践

### 1. 环境检查

始终检查 Fiber 支持：

```php
if (!Environment::checkFiberSupport()) {
    $this->markTestSkipped('Fiber support is not available in this environment.');
}
```

### 2. 使用 @covers 注解

使用 @covers 注解明确指定测试覆盖的方法：

```php
/**
 * @covers \Nova\Fibers\Core\EventLoop::defer
 */
public function testDefer(): void
```

### 3. 清理资源

测试完成后清理使用的资源：

```php
public function testOnReadable(): void
{
    // 测试代码...
    
    // 清理资源
    fclose($sockets[0]);
    fclose($sockets[1]);
}
```

### 4. 测试边界条件

测试各种边界条件和错误情况：

```php
public function testCancelNonExistentTask(): void
{
    $scheduler = new LocalScheduler();
    
    // 测试取消不存在的任务
    $result = $scheduler->cancel('non-existent-task');
    $this->assertFalse($result);
}
```

### 5. 使用数据提供者

对于需要测试多种输入的测试，使用数据提供者：

```php
/**
 * @dataProvider taskStatusProvider
 */
public function testTaskStatus(string $initialStatus, string $expectedStatus): void
{
    // 测试代码
}

public function taskStatusProvider(): array
{
    return [
        ['pending', 'pending'],
        ['running', 'running'],
        ['completed', 'completed']
    ];
}
```

## 常见问题

### 1. 测试跳过

如果环境不支持某些功能，使用 `markTestSkipped`：

```php
if (!function_exists('pcntl_signal')) {
    $this->markTestSkipped('pcntl extension is not available.');
}
```

### 2. 测试异步代码

测试异步代码时，使用 usleep 等待异步操作完成，或使用反射直接调用内部方法。

### 3. 测试私有方法

使用反射来测试私有方法：

```php
$reflection = new \ReflectionClass($object);
$method = $reflection->getMethod('privateMethod');
$method->setAccessible(true);
$result = $method->invoke($object, $parameters);
```

## 添加新测试

要添加新测试，请按照以下步骤操作：

1. 在 [tests](file:///d:/wwwroot/composer/fibers/tests/) 目录中创建新的测试文件，文件名以 Test.php 结尾
2. 继承 `PHPUnit\Framework\TestCase`
3. 添加适当的环境检查
4. 编写测试方法，每个方法测试一个特定功能
5. 使用 @covers 注解指定测试覆盖的方法
6. 运行测试确保新测试通过

通过遵循这些指南，您可以确保为 nova/fibers 包编写高质量的测试，提高代码的可靠性和可维护性。