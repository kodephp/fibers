# Advanced PHP Features 高级PHP特性

## 概述

本文档详细介绍了 nova/fibers 包中使用的高级PHP特性，包括协变/逆变、反射API、类型安全和快速调用等方面。这些特性使包更加健壮、安全和高效。

## 目录

- 类型系统与严格类型
- Attributes 用法
- 反射机制在容器中的应用
- 纤程中的析构限制与规避
- 错误与异常处理建议

## 类型系统与严格类型

项目默认开启 `declare(strict_types=1);`，建议：
- 使用明确的返回类型和参数类型
- 避免使用 `mixed`，除非必要

## Attributes 用法

`src/Attributes/` 中提供了若干 Attribute 标记，例如 `#[Timeout]`、`#[FiberSafe]`、`#[ChannelListener]`。可用于标注方法超时、可安全运行于纤程、或订阅消息通道等。

## 反射机制在容器中的应用

`Support\\Container` 使用反射自动解析构造参数，减少手工装配成本。请确保构造函数参数可被容器解析。

## 纤程中的析构限制与规避

在 PHP < 8.4 的版本中，不允许在 `__destruct` 中进行 `Fiber::suspend()`。通过 `Support\\Environment::shouldEnableSafeDestructMode()` 判断并规避。

## 错误与异常处理建议

- 纤程中的长时间操作应设置超时
- 谨慎在析构中做阻塞性工作

## 协变与逆变 (Covariance and Contravariance)

### 协变 (Covariance)

协变允许子类方法返回比父类更具体的类型。在 nova/fibers 中，我们广泛使用协变来提供更精确的类型提示。

```php
// 基础接口
interface FiberSchedulerInterface 
{
    public function submit(callable $task): string;
}

// 实现类使用协变返回更具体的类型
class LocalScheduler implements FiberSchedulerInterface
{
    // 协变：返回类型可以是父类方法返回类型的子类型
    public function submit(callable $task): string 
    {
        // 实现细节
        return uniqid('task_', true);
    }
}

// 更具体的实现
class DistributedScheduler extends LocalScheduler
{
    // 进一步协变：返回带有更多上下文的ID
    public function submit(callable $task): string 
    {
        // 实现细节
        return 'distributed_' . uniqid('task_', true);
    }
}
```

### 逆变 (Contravariance)

逆变允许子类方法接受比父类更通用的参数类型。在事件处理和回调函数中特别有用。

```php
// 基础事件接口
interface EventInterface 
{
    public function getName(): string;
}

// 具体事件
class UserLoginEvent implements EventInterface 
{
    public function getName(): string 
    {
        return 'user.login';
    }
    
    public function getUserId(): int 
    {
        // ...
    }
}

// 事件监听器接口
interface EventListenerInterface 
{
    // 逆变：接受更通用的EventInterface类型
    public function handle(EventInterface $event): void;
}

// 具体监听器可以处理更具体的事件类型
class UserLoginListener implements EventListenerInterface 
{
    // 逆变：可以接受EventInterface的任何子类型
    public function handle(EventInterface $event): void 
    {
        if ($event instanceof UserLoginEvent) {
            // 处理用户登录事件
            echo "User {$event->getUserId()} logged in\n";
        }
    }
}
```

## 反射API (Reflection API)

反射API在 nova/fibers 中用于动态分析和调用函数，提供更安全和快速的调用机制。

### 动态方法调用

```php
use ReflectionClass;
use ReflectionMethod;

class FiberTaskExecutor 
{
    /**
     * 使用反射安全地调用方法
     *
     * @param object $object 对象实例
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     * @throws ReflectionException
     */
    public function invokeMethod(object $object, string $method, array $args = []) 
    {
        $reflection = new ReflectionClass($object);
        
        // 检查方法是否存在
        if (!$reflection->hasMethod($method)) {
            throw new \RuntimeException("Method {$method} not found");
        }
        
        $reflectionMethod = $reflection->getMethod($method);
        
        // 检查方法是否可访问
        if (!$reflectionMethod->isPublic()) {
            throw new \RuntimeException("Method {$method} is not public");
        }
        
        // 检查参数数量
        $parameters = $reflectionMethod->getParameters();
        if (count($parameters) > count($args)) {
            throw new \RuntimeException("Not enough arguments for method {$method}");
        }
        
        // 调用方法
        return $reflectionMethod->invokeArgs($object, $args);
    }
}

// 使用示例
class UserService 
{
    public function getUserById(int $id): array 
    {
        return ['id' => $id, 'name' => 'User ' . $id];
    }
}

$executor = new FiberTaskExecutor();
$service = new UserService();

try {
    $result = $executor->invokeMethod($service, 'getUserById', [123]);
    print_r($result);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### 属性分析

```php
use ReflectionClass;
use ReflectionAttribute;

// 分析类上的属性
class AttributeAnalyzer 
{
    /**
     * 获取类上的所有属性
     *
     * @param string|object $class 类名或实例
     * @return array
     */
    public function getClassAttributes($class): array 
    {
        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes();
        
        $result = [];
        foreach ($attributes as $attribute) {
            $result[] = [
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
                'instance' => $attribute->newInstance()
            ];
        }
        
        return $result;
    }
    
    /**
     * 获取方法上的属性
     *
     * @param string|object $class 类名或实例
     * @param string $method 方法名
     * @return array
     */
    public function getMethodAttributes($class, string $method): array 
    {
        $reflection = new ReflectionClass($class);
        $reflectionMethod = $reflection->getMethod($method);
        $attributes = $reflectionMethod->getAttributes();
        
        $result = [];
        foreach ($attributes as $attribute) {
            $result[] = [
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
                'instance' => $attribute->newInstance()
            ];
        }
        
        return $result;
    }
}
```

## 类型安全和快速调用

### 使用模板类型提高类型安全性

```php
/**
 * 泛型模式的通道实现
 *
 * @template T
 */
class TypedChannel 
{
    /** @var mixed[] */
    private array $queue = [];
    
    /** @var bool */
    private bool $closed = false;
    
    /**
     * 推送值到通道
     *
     * @param T $value
     * @return void
     */
    public function push(mixed $value): void 
    {
        if ($this->closed) {
            throw new \RuntimeException("Cannot push to closed channel");
        }
        
        $this->queue[] = $value;
    }
    
    /**
     * 从通道弹出值
     *
     * @return T|null
     */
    public function pop(): mixed 
    {
        if (empty($this->queue)) {
            return null;
        }
        
        return array_shift($this->queue);
    }
    
    /**
     * 关闭通道
     *
     * @return void
     */
    public function close(): void 
    {
        $this->closed = true;
    }
}

// 使用示例
/** @var TypedChannel<string> $stringChannel */
$stringChannel = new TypedChannel();
$stringChannel->push("Hello");
$stringChannel->push("World");

// 静态分析工具可以识别这里的类型
$message = $stringChannel->pop(); // 被识别为 string|null
```

### 快速调用优化

```php
class FastCallOptimizer 
{
    /**
     * 缓存反射方法以提高性能
     *
     * @var array<string, ReflectionMethod>
     */
    private static array $methodCache = [];
    
    /**
     * 快速调用对象方法
     *
     * @param object $object 对象实例
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     */
    public static function callMethod(object $object, string $method, array $args = []) 
    {
        $key = get_class($object) . '::' . $method;
        
        // 使用缓存避免重复创建ReflectionMethod
        if (!isset(self::$methodCache[$key])) {
            self::$methodCache[$key] = new ReflectionMethod($object, $method);
        }
        
        $reflectionMethod = self::$methodCache[$key];
        
        // 直接调用方法
        return $reflectionMethod->invokeArgs($object, $args);
    }
    
    /**
     * 清理方法缓存
     *
     * @return void
     */
    public static function clearCache(): void 
    {
        self::$methodCache = [];
    }
}
```

## 继承、工厂和接口设计

### 工厂模式

```php
/**
 * Fiber调度器工厂
 */
class FiberSchedulerFactory 
{
    /**
     * 创建调度器实例
     *
     * @param string $type 调度器类型
     * @param array $config 配置参数
     * @return DistributedSchedulerInterface
     */
    public static function create(string $type, array $config = []): DistributedSchedulerInterface 
    {
        switch ($type) {
            case 'local':
                return new LocalScheduler($config);
                
            case 'distributed':
                // 在实际实现中，这里会创建分布式调度器
                return new LocalScheduler($config);
                
            default:
                throw new \InvalidArgumentException("Unknown scheduler type: {$type}");
        }
    }
    
    /**
     * 从配置创建调度器
     *
     * @param array $config 配置数组
     * @return DistributedSchedulerInterface
     */
    public static function createFromConfig(array $config): DistributedSchedulerInterface 
    {
        $type = $config['type'] ?? 'local';
        $options = $config['options'] ?? [];
        
        return self::create($type, $options);
    }
}
```

### 接口隔离原则

```php
// 细粒度接口设计
interface TaskSubmitterInterface 
{
    public function submit(callable $task): string;
}

interface TaskResultInterface 
{
    public function getResult(string $taskId, ?float $timeout = null): mixed;
}

interface TaskControlInterface 
{
    public function cancel(string $taskId): bool;
    public function getStatus(string $taskId): string;
}

interface ClusterInfoInterface 
{
    public function getClusterInfo(): array;
}

// 组合接口
interface DistributedSchedulerInterface extends 
    TaskSubmitterInterface,
    TaskResultInterface,
    TaskControlInterface,
    ClusterInfoInterface
{
    // 可以添加特定于分布式调度器的方法
}

// 具体实现只需要实现必要的接口
class SimpleTaskRunner implements TaskSubmitterInterface, TaskResultInterface 
{
    private array $tasks = [];
    
    public function submit(callable $task): string 
    {
        $taskId = uniqid('task_', true);
        $this->tasks[$taskId] = [
            'task' => $task,
            'status' => 'pending',
            'result' => null
        ];
        
        // 立即执行任务
        $this->executeTask($taskId);
        
        return $taskId;
    }
    
    public function getResult(string $taskId, ?float $timeout = null): mixed 
    {
        if (!isset($this->tasks[$taskId])) {
            throw new \RuntimeException("Task not found: {$taskId}");
        }
        
        // 简化的结果获取
        return $this->tasks[$taskId]['result'];
    }
    
    private function executeTask(string $taskId): void 
    {
        $task = $this->tasks[$taskId]['task'];
        try {
            $this->tasks[$taskId]['result'] = $task();
            $this->tasks[$taskId]['status'] = 'completed';
        } catch (\Throwable $e) {
            $this->tasks[$taskId]['result'] = $e;
            $this->tasks[$taskId]['status'] = 'failed';
        }
    }
}
```

## 自定义扩展

### 创建自定义属性

```php
// 自定义属性示例
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Retry 
{
    public function __construct(
        public int $attempts = 3,
        public float $delay = 1.0
    ) {}
}

// 使用自定义属性
class ApiClient 
{
    #[Retry(attempts: 5, delay: 0.5)]
    public function fetchUserData(int $userId): array 
    {
        // 可能失败的API调用
        // 属性提供重试信息
        return [];
    }
}

// 属性处理器
class AttributeHandler 
{
    public function handleRetry(object $object, string $method, array $args = []): mixed 
    {
        $reflection = new ReflectionMethod($object, $method);
        $attributes = $reflection->getAttributes(Retry::class);
        
        if (empty($attributes)) {
            // 没有Retry属性，直接调用
            return $reflection->invokeArgs($object, $args);
        }
        
        // 获取Retry属性
        $retry = $attributes[0]->newInstance();
        
        $lastException = null;
        for ($i = 0; $i < $retry->attempts; $i++) {
            try {
                return $reflection->invokeArgs($object, $args);
            } catch (\Exception $e) {
                $lastException = $e;
                if ($i < $retry->attempts - 1) {
                    // 等待后重试
                    usleep((int)($retry->delay * 1000000));
                }
            }
        }
        
        throw $lastException;
    }
}
```

## 最佳实践

1. **合理使用协变和逆变**：只在确实需要更灵活的类型关系时使用，避免过度设计。

2. **缓存反射对象**：反射操作相对昂贵，应该缓存 `ReflectionClass` 和 `ReflectionMethod` 对象以提高性能。

3. **类型提示**：尽可能使用详细的类型提示，包括模板类型，以提高代码的可读性和静态分析能力。

4. **接口隔离**：遵循接口隔离原则，创建细粒度的接口，让实现类只实现需要的方法。

5. **错误处理**：在使用反射时，始终处理可能的 `ReflectionException` 异常。

6. **文档注释**：使用PHPDoc为复杂类型和模板提供清晰的文档。

通过合理运用这些高级PHP特性，nova/fibers 包能够提供类型安全、高性能和易于扩展的功能，同时保持代码的清晰性和可维护性。