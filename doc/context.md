# Context 详解

## 概述

Context 是 nova/fibers 提供的上下文管理组件，用于在纤程之间传递请求范围的值、取消信号和超时控制。它借鉴了 Go 语言的 context 包设计，为 PHP 应用提供了一种优雅的上下文管理方式。

## 基本概念

### 上下文

上下文是一个携带截止时间、取消信号和其他跨 API 边界传递的请求范围值的对象。它主要用于在纤程树中传递请求相关的数据和控制信号。

### 取消信号

上下文可以携带取消信号，当父上下文被取消时，所有从它派生的子上下文也会被取消。

## 使用方法

### 创建上下文

```php
use Nova\Fibers\Context\Context;

// 创建空的上下文
$context = new Context();

// 创建带超时的上下文
$timeoutContext = Context::withTimeout(new Context(), 5.0); // 5秒超时

// 创建带截止时间的上下文
$deadlineContext = Context::withDeadline(new Context(), time() + 10); // 10秒后截止

// 创建带值的上下文
$valueContext = Context::withValue(new Context(), 'user_id', 12345);
```

### 取消上下文

```php
use Nova\Fibers\Context\Context;

// 创建可取消的上下文
[$context, $cancel] = Context::withCancel(new Context());

// 在某个时刻取消上下文
$cancel();

// 检查上下文是否被取消
if ($context->isCancelled()) {
    echo "Context has been cancelled\n";
    echo "Cancel reason: " . $context->cancelReason() . "\n";
}
```

### 从上下文获取值

```php
use Nova\Fibers\Context\Context;

$context = Context::withValue(new Context(), 'request_id', 'abc-123');
$context = Context::withValue($context, 'user_id', 42);

// 获取值
$requestId = $context->value('request_id');
$userId = $context->value('user_id');

echo "Request ID: $requestId\n"; // 输出: Request ID: abc-123
echo "User ID: $userId\n";       // 输出: User ID: 42

// 获取不存在的值返回 null
$missing = $context->value('missing_key');
var_dump($missing); // 输出: NULL
```

### 在纤程中使用上下文

```php
use Nova\Fibers\Context\Context;
use Nova\Fibers\FiberPool;

// 创建带超时的上下文
$context = Context::withTimeout(new Context(), 3.0);

// 在纤程池中使用上下文
FiberPool::submit(function() use ($context) {
    // 模拟长时间运行的任务
    for ($i = 0; $i < 10; $i++) {
        // 定期检查上下文是否被取消
        if ($context->isCancelled()) {
            echo "Task cancelled: " . $context->cancelReason() . "\n";
            return;
        }
        
        echo "Working... step $i\n";
        usleep(500000); // 0.5秒
    }
    
    echo "Task completed\n";
});
```

## 上下文属性

### 超时和截止时间

```php
use Nova\Fibers\Context\Context;

// 创建带超时的上下文
$context = Context::withTimeout(new Context(), 5.0);

// 检查是否有截止时间
if ($context->hasDeadline()) {
    echo "Deadline: " . date('Y-m-d H:i:s', $context->deadline()) . "\n";
} else {
    echo "No deadline set\n";
}

// 获取剩余时间
$remaining = $context->remainingTime();
if ($remaining !== null) {
    echo "Remaining time: {$remaining}s\n";
}
```

### 取消原因

```php
use Nova\Fibers\Context\Context;

[$context, $cancel] = Context::withCancel(new Context());

// 取消上下文并提供原因
$cancel('User requested cancellation');

if ($context->isCancelled()) {
    echo "Cancelled reason: " . $context->cancelReason() . "\n";
    // 输出: Cancelled reason: User requested cancellation
}
```

## 高级用法

### 上下文监听器

可以为上下文添加监听器，在上下文被取消时执行特定操作。

```php
use Nova\Fibers\Context\Context;

[$context, $cancel] = Context::withCancel(new Context());

// 添加取消监听器
$context->addOnCancelListener(function($reason) {
    echo "Context cancelled with reason: $reason\n";
    // 执行清理操作
    // 例如：关闭数据库连接、释放资源等
});

// 添加另一个监听器
$context->addOnCancelListener(function($reason) {
    // 记录日志
    error_log("Operation cancelled: $reason");
});

// 触发取消
$cancel('System shutdown');
```

### 上下文传播

上下文会自动传播到子纤程中。

```php
use Nova\Fibers\Context\Context;
use Nova\Fibers\FiberPool;

function processWithContext(Context $context, $data) {
    return FiberPool::submit(function() use ($context, $data) {
        // 检查上下文是否被取消
        if ($context->isCancelled()) {
            throw new Exception("Operation cancelled: " . $context->cancelReason());
        }
        
        // 模拟处理过程
        usleep(1000000); // 1秒
        
        // 再次检查
        if ($context->isCancelled()) {
            throw new Exception("Operation cancelled: " . $context->cancelReason());
        }
        
        return "Processed: $data";
    });
}

// 创建带超时的上下文
$context = Context::withTimeout(new Context(), 2.0);

try {
    $result = processWithContext($context, "important data");
    echo "Result: $result\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### 自定义上下文

可以通过继承 Context 类来创建自定义上下文。

```php
use Nova\Fibers\Context\Context;

class RequestContext extends Context 
{
    public function getUserId(): ?int 
    {
        return $this->value('user_id');
    }
    
    public function getRequestId(): ?string 
    {
        return $this->value('request_id');
    }
    
    public function getLocale(): string 
    {
        return $this->value('locale') ?? 'en';
    }
}

// 使用自定义上下文
$context = new RequestContext();
$context = Context::withValue($context, 'user_id', 12345);
$context = Context::withValue($context, 'request_id', 'req-abc-123');
$context = Context::withValue($context, 'locale', 'zh-CN');

echo "User ID: " . $context->getUserId() . "\n";
echo "Request ID: " . $context->getRequestId() . "\n";
echo "Locale: " . $context->getLocale() . "\n";
```

## 最佳实践

1. **及时传递上下文**：在函数调用和纤程创建时及时传递上下文。
2. **合理设置超时**：根据业务需求合理设置上下文的超时时间。
3. **正确处理取消**：在长时间运行的操作中定期检查上下文是否被取消。
4. **避免上下文污染**：不要在上下文中存储大量数据或敏感信息。
5. **使用类型安全的访问方法**：通过自定义上下文类提供类型安全的值访问方法。

## 故障排除

### 上下文未正确传播

检查是否在所有函数调用和纤程创建时都正确传递了上下文。

### 取消信号未生效

检查是否在关键操作前检查了上下文的取消状态。

### 超时设置不当

检查超时时间是否合理，过短的超时可能导致正常操作被中断。

## 参考资料

- [Go Context Package](https://pkg.go.dev/context)
- [Context Pattern in Concurrent Programming](https://en.wikipedia.org/wiki/Context_pattern)
- [PHP Fibers RFC](https://wiki.php.net/rfc/fibers)