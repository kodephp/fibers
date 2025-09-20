# Attributes (注解) 详解

## 概述

Attributes 是 nova/fibers 提供的注解系统，它利用 PHP 8.1+ 的原生 Attribute 特性，为纤程应用提供了一种声明式的配置方式。通过注解，可以轻松地为函数和方法添加超时控制、重试策略、优先级等特性。

## 基本概念

### 注解

注解是一种特殊的类，可以通过 PHP 8.1+ 的 Attribute 语法附加到类、方法、函数、参数等代码元素上。它们提供了一种结构化的方式来添加元数据。

### IDE 支持

nova/fibers 的注解系统完全兼容现代 IDE，可以提供代码补全、错误检查和文档提示等功能。

## 使用方法

### Timeout 注解

Timeout 注解用于为函数或方法设置执行超时时间。

```php
use Nova\Fibers\Attributes\Timeout;

#[Timeout(5.0)] // 5秒超时
function fetchDataFromAPI() 
{
    // 模拟 API 调用
    sleep(3);
    return "API Data";
}

// 使用示例
use Nova\Fibers\AutomaticTimeoutHandler;

$handler = new AutomaticTimeoutHandler();
$result = $handler->applyTimeout('fetchDataFromAPI');
echo $result; // 输出: API Data
```

### Retry 注解

Retry 注解用于为函数或方法设置重试策略。

```php
use Nova\Fibers\Attributes\Retry;

#[Retry(maxAttempts: 3, delay: 1000)] // 最多重试3次，每次间隔1秒
function unreliableOperation() 
{
    // 模拟可能失败的操作
    if (rand(0, 1) === 0) {
        throw new Exception("Operation failed");
    }
    return "Success";
}

// 使用示例
try {
    $result = unreliableOperation();
    echo $result;
} catch (Exception $e) {
    echo "Operation failed after retries: " . $e->getMessage();
}
```

### Priority 注解

Priority 注解用于为任务设置优先级。

```php
use Nova\Fibers\Attributes\Priority;
use Nova\Fibers\Scheduler\LocalScheduler;

#[Priority(10)] // 高优先级
function highPriorityTask() 
{
    return "High priority task completed";
}

#[Priority(1)] // 低优先级
function lowPriorityTask() 
{
    return "Low priority task completed";
}

// 使用示例
$scheduler = new LocalScheduler();
$highTaskId = $scheduler->submit('highPriorityTask');
$lowTaskId = $scheduler->submit('lowPriorityTask');

$highResult = $scheduler->getResult($highTaskId);
$lowResult = $scheduler->getResult($lowTaskId);

echo $highResult . "\n"; // 更可能先执行
echo $lowResult . "\n";
```

### Backoff 注解

Backoff 注解用于为重试操作设置退避策略。

```php
use Nova\Fibers\Attributes\Backoff;

#[Backoff(strategy: 'exponential', baseDelay: 1000, maxDelay: 10000)]
function networkOperation() 
{
    // 模拟网络操作
    if (rand(0, 2) !== 0) {
        throw new Exception("Network error");
    }
    return "Network operation succeeded";
}

// 使用示例
try {
    $result = networkOperation();
    echo $result;
} catch (Exception $e) {
    echo "Network operation failed after backoff retries: " . $e->getMessage();
}
```

## 组合使用注解

多个注解可以组合使用，以实现更复杂的功能。

```php
use Nova\Fibers\Attributes\Timeout;
use Nova\Fibers\Attributes\Retry;
use Nova\Fibers\Attributes\Backoff;

#[Timeout(10.0)]
#[Retry(maxAttempts: 3)]
#[Backoff(strategy: 'exponential', baseDelay: 500, maxDelay: 5000)]
function complexOperation() 
{
    // 复杂操作，可能需要较长时间且可能失败
    if (rand(0, 5) !== 0) {
        throw new Exception("Complex operation failed");
    }
    
    sleep(rand(1, 3)); // 模拟耗时操作
    return "Complex operation succeeded";
}

// 使用 AutomaticTimeoutHandler 应用注解
use Nova\Fibers\AutomaticTimeoutHandler;

$handler = new AutomaticTimeoutHandler();
$result = $handler->applyTimeout('complexOperation');
echo $result;
```

## 自定义注解

可以通过继承 Attribute 基类来创建自定义注解。

```php
use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class Cacheable 
{
    public function __construct(
        public readonly string $key,
        public readonly int $ttl = 3600
    ) {}
}

// 使用自定义注解
#[Cacheable(key: 'user_profile_123', ttl: 7200)]
function getUserProfile(int $userId) 
{
    // 获取用户资料的逻辑
    return ['id' => $userId, 'name' => 'John Doe'];
}

// 注解处理器
class CacheableHandler 
{
    public function handle(string $functionName, array $args) 
    {
        // 通过反射获取注解
        $reflection = new ReflectionFunction($functionName);
        $attributes = $reflection->getAttributes(Cacheable::class);
        
        if (!empty($attributes)) {
            $cacheable = $attributes[0]->newInstance();
            
            // 检查缓存
            $cached = $this->getFromCache($cacheable->key);
            if ($cached !== null) {
                return $cached;
            }
            
            // 执行函数并缓存结果
            $result = call_user_func_array($functionName, $args);
            $this->saveToCache($cacheable->key, $result, $cacheable->ttl);
            return $result;
        }
        
        // 没有注解，直接执行
        return call_user_func_array($functionName, $args);
    }
    
    private function getFromCache(string $key) 
    {
        // 缓存获取逻辑
        return null;
    }
    
    private function saveToCache(string $key, $value, int $ttl) 
    {
        // 缓存保存逻辑
    }
}
```

## IDE 支持和代码提示

nova/fibers 的注解系统完全支持 IDE 代码提示和静态分析工具。

```php
/**
 * @method static mixed run(callable $task, float $timeout = null)
 * @method static FiberPool pool(array $options = [])
 */
class Fiber {}

/**
 * @method static string submit(callable $task, ?Context $context = null, array $options = [])
 * @method static mixed getResult(string $taskId, ?float $timeout = null)
 */
class LocalScheduler {}
```

通过以上 PHPDoc 注释，现代 IDE 可以提供完整的代码补全和参数提示功能。

## 最佳实践

1. **合理使用注解**：不要过度使用注解，保持代码的可读性。
2. **统一注解风格**：在项目中统一注解的使用风格和命名规范。
3. **提供默认值**：为注解参数提供合理的默认值。
4. **文档化注解**：为自定义注解提供详细的文档说明。
5. **测试注解逻辑**：为注解的处理逻辑编写单元测试。

## 故障排除

### 注解未生效

检查是否正确使用了 Attribute 语法，以及注解处理器是否正确注册。

### IDE 无提示

确保 IDE 支持 PHP 8.1+ 的 Attribute 特性，并正确配置了项目。

### 注解参数错误

检查注解参数的类型和值是否符合要求。

## 参考资料

- [PHP Attributes RFC](https://wiki.php.net/rfc/attributes_v2)
- [PHP Reflection API](https://www.php.net/manual/en/book.reflection.php)
- [Modern PHP Development Practices](https://php.watch/articles/php-8-attributes)