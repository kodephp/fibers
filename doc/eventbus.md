# EventBus 详解

## 概述

EventBus 是 nova/fibers 的事件总线组件，用于实现发布/订阅模式。它允许组件之间通过事件进行解耦通信，支持同步和异步事件处理。

## 基本概念

### 事件

事件是 EventBus 中传递的数据单元，通常包含事件类型和相关数据。

### 订阅者

订阅者是监听特定事件类型的回调函数或方法。

### 发布者

发布者是触发事件的对象或函数。

## 使用方法

### 基本使用

```php
use Nova\Fibers\Event\EventBus;

// 订阅事件
EventBus::on('user.registered', function($event) {
    echo "User registered: " . $event->data['name'];
});

// 发布事件
EventBus::fire('user.registered', ['name' => 'John Doe', 'email' => 'john@example.com']);
```

### 使用类方法作为订阅者

```php
class UserService 
{
    public function onUserRegistered($event) 
    {
        // 处理用户注册事件
        $this->sendWelcomeEmail($event->data['email']);
    }
    
    private function sendWelcomeEmail($email) 
    {
        // 发送欢迎邮件
    }
}

$userService = new UserService();
EventBus::on('user.registered', [$userService, 'onUserRegistered']);
```

### 异步事件处理

```php
use Nova\Fibers\Event\EventBus;

// 异步订阅事件
EventBus::onAsync('user.registered', function($event) {
    // 异步处理事件
    // 例如：发送通知、更新统计数据等
    echo "Async processing for user: " . $event->data['name'];
});

// 发布事件（异步处理）
EventBus::fire('user.registered', ['name' => 'John Doe']);
```

## 事件属性

### 优先级

订阅者可以设置优先级，优先级高的订阅者会先执行。

```php
use Nova\Fibers\Attributes\Priority;

EventBus::on('user.registered', function($event) {
    echo "High priority handler";
}, 10); // 优先级为10

EventBus::on('user.registered', function($event) {
    echo "Low priority handler";
}, 1); // 优先级为1
```

### 条件订阅

订阅者可以设置条件，只有满足条件时才会执行。

```php
EventBus::on('order.created', function($event) {
    echo "Processing high value order";
}, 0, function($event) {
    return $event->data['amount'] > 1000; // 只处理金额大于1000的订单
});
```

## 高级用法

### 自定义事件类

```php
use Nova\Fibers\Event\Event;

class UserRegisteredEvent extends Event 
{
    public function __construct($userData) 
    {
        parent::__construct('user.registered', $userData);
    }
    
    public function getUserName() 
    {
        return $this->data['name'];
    }
    
    public function getUserEmail() 
    {
        return $this->data['email'];
    }
}

// 使用自定义事件类
EventBus::on('user.registered', function(UserRegisteredEvent $event) {
    echo "Welcome " . $event->getUserName();
});

// 发布自定义事件
$event = new UserRegisteredEvent(['name' => 'John Doe', 'email' => 'john@example.com']);
EventBus::fire($event);
```

### 事件中间件

EventBus 支持事件中间件，可以在事件处理前后执行额外逻辑。

```php
use Nova\Fibers\Event\EventBus;

// 添加事件中间件
EventBus::addMiddleware(function($event, callable $next) {
    echo "Before event processing\n";
    $result = $next($event);
    echo "After event processing\n";
    return $result;
});

EventBus::on('user.registered', function($event) {
    echo "Processing user registration\n";
});
```

### 事件统计和监控

```php
use Nova\Fibers\Event\EventBus;

// 获取事件统计信息
$stats = EventBus::getStats();
print_r($stats);

// 重置统计信息
EventBus::resetStats();
```

## 最佳实践

1. **合理命名事件**：使用清晰、一致的事件命名规范。
2. **避免复杂逻辑**：事件处理函数应该尽量简单，复杂逻辑应该放在专门的服务类中。
3. **异常处理**：在事件处理函数中正确处理异常，避免影响其他订阅者。
4. **性能考虑**：对于耗时操作，考虑使用异步处理。
5. **测试**：为事件处理逻辑编写单元测试。

## 故障排除

### 事件未触发

检查事件是否正确发布，以及订阅者是否正确注册。

### 订阅者未执行

检查事件名称是否匹配，以及订阅者的条件是否满足。

### 性能问题

检查是否有过多的订阅者或复杂的事件处理逻辑，考虑使用异步处理。

## 参考资料

- [观察者模式](https://en.wikipedia.org/wiki/Observer_pattern)
- [发布/订阅模式](https://en.wikipedia.org/wiki/Publish%E2%80%93subscribe_pattern)