# 进阶使用指南

本指南将深入介绍 `Kode/fibers` 的高级功能和最佳实践，帮助您在生产环境中充分发挥其潜力。

## 纤程池高级配置

### 1. 动态池大小

根据系统负载动态调整纤程池大小：

```php
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Support\CpuInfo;

$pool = new FiberPool([
    'size' => CpuInfo::get() * 4,         // 初始大小
    'min_size' => CpuInfo::get() * 2,     // 最小大小
    'max_size' => CpuInfo::get() * 8,     // 最大大小
    'scaling_threshold' => 0.8,           // 使用率达到80%时扩展
    'scaling_down_delay' => 60,           // 缩容延迟（秒）
]);
```

### 2. 自定义事件回调

为纤程池添加生命周期事件回调：

```php
$pool = new FiberPool([
    'name' => 'api-worker',
    'onCreate' => function ($fiberId) {
        // 记录纤程创建
        logger("Fiber #$fiberId created");
        // 初始化纤程特定资源
    },
    'onDestroy' => function ($fiberId) {
        // 清理纤程资源
        logger("Fiber #$fiberId destroyed");
    },
    'onTaskStart' => function ($taskId, $fiberId) {
        // 任务开始处理
        logger("Task $taskId started on fiber $fiberId");
    },
    'onTaskComplete' => function ($taskId, $result, $durationMs) {
        // 任务完成
        logger("Task $taskId completed in {$durationMs}ms");
    },
    'onTaskFail' => function ($taskId, $exception, $attempt) {
        // 任务失败
        logger("Task $taskId failed (attempt #$attempt): " . $exception->getMessage());
    },
]);
```

## 高级通道通信

### 1. 生产者-消费者模式

实现高效的生产者-消费者模式：

```php
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Facades\Fiber;

// 创建一个带缓冲区的通道
$queue = Channel::make('task-queue', 100);

// 启动多个消费者
for ($i = 0; $i < 5; $i++) {
    Fiber::run(function () use ($queue, $i) {
        $consumerId = "consumer-$i";
        echo "$consumerId started\n";
        
        while (true) {
            try {
                // 接收任务，带超时以便定期检查是否需要退出
                $task = $queue->pop(5.0);
                echo "$consumerId processing task: " . json_encode($task) . "\n";
                
                // 模拟任务处理
                Fiber::sleep(0.5);
                
            } catch (\RuntimeException $e) {
                // 超时，继续循环
                continue;
            } catch (\Kode\Fibers\Exceptions\ChannelClosedException $e) {
                // 通道关闭，退出消费者
                echo "$consumerId exiting\n";
                break;
            }
        }
    });
}

// 生产者
try {
    for ($i = 1; $i <= 100; $i++) {
        $task = ['id' => $i, 'data' => "Task data $i"];
        // 带超时的推送，防止缓冲区满导致阻塞
        $queue->push($task, 2.0);
        echo "Produced task $i\n";
    }
} finally {
    // 所有任务推送完成后关闭通道
    $queue->close();
}
```

### 2. 专用服务通道

使用专用通道集成各种服务：

```php
use Kode\Fibers\Channel\Channel;

// 创建MySQL专用通道
$dbChannel = Channel::mysql(
    'main-db',
    'mysql:host=localhost;dbname=test;charset=utf8mb4',
    'username',
    'password',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// 创建Redis专用通道
$redisChannel = Channel::redis(
    'cache',
    '127.0.0.1',
    6379,
    'redis_password',
    0
);

// 创建HTTP请求通道
$httpChannel = Channel::http(
    'api-client',
    [
        'timeout' => 10,
        'headers' => [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Kode/Fibers Client'
        ]
    ]
);

// 使用MySQL通道
Fiber::run(function () use ($dbChannel) {
    // 发送查询请求到通道
    $users = $dbChannel->pop(function (PDO $pdo) {
        $stmt = $pdo->prepare('SELECT * FROM users LIMIT 10');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }, 5.0);
    
    print_r($users);
});

// 使用Redis通道
Fiber::run(function () use ($redisChannel) {
    // 发送Redis命令到通道
    $value = $redisChannel->pop(function (Redis $redis) {
        return $redis->get('cache:key');
    }, 2.0);
    
    echo "Cache value: $value\n";
});

// 使用HTTP通道
Fiber::run(function () use ($httpChannel) {
    // 发送HTTP请求到通道
    $response = $httpChannel->pop(function ($client) {
        return $client->get('https://api.example.com/users');
    }, 10.0);
    
    print_r($response);
});
```

## 高级任务管理

### 1. 重试策略

实现复杂的任务重试策略：

```php
use Kode\Fibers\Task\RetryableTask;
use Kode\Fibers\Facades\Fiber;

// 创建一个具有高级重试策略的任务
$task = new RetryableTask(
    function () {
        // 模拟网络请求
        $response = @file_get_contents('https://api.example.com/data');
        if ($response === false) {
            throw new \RuntimeException('Network error');
        }
        return $response;
    },
    maxRetries: 5,
    retryDelay: 1.0,
    retryOn: [\RuntimeException::class, \PDOException::class],
    doNotRetryOn: [\LogicException::class]
);

// 添加指数退避和随机抖动
$task->withExponentialBackoff(1.5)->withJitter(0.3);

// 执行任务
$result = Fiber::run($task);

// 或者使用函数式风格
$result = Fiber::retry(
    function () {
        // 任务实现
    },
    maxRetries: 3,
    retryDelay: 0.5,
    // 自定义退避策略
    backoffStrategy: fn($attempt) => min(10, 0.1 * pow(2, $attempt))
);
```

### 2. 任务优先级

实现任务优先级队列：

```php
use Kode\Fibers\Task\TaskRunner;

// 定义不同优先级的任务
$tasks = [
    [fn() => 'Low priority task', 1],      // 低优先级
    [fn() => 'Medium priority task', 3],   // 中优先级
    [fn() => 'High priority task', 5],     // 高优先级
    [fn() => 'Urgent task', 10]            // 紧急任务
];

// 按优先级执行
$results = TaskRunner::prioritized($tasks);

// 结果顺序会按照优先级排序，高优先级任务先完成
print_r($results);
```

### 3. 可取消任务

创建可在需要时取消的任务：

```php
use Kode\Fibers\Task\TaskRunner;
use Kode\Fibers\Facades\Fiber;

// 创建可取消任务
[$cancellableTask, $cancelFn] = TaskRunner::cancellable(function () {
    $i = 0;
    while (true) {
        echo "Processing item $i\n";
        Fiber::sleep(0.1); // 允许在此处被中断
        $i++;
    }
    return 'This will never return';
});

// 启动任务
Fiber::run($cancellableTask);

// 500ms后取消任务
Fiber::sleep(0.5);
$cancelFn();
echo "Task cancelled\n";
```

## 高级上下文管理

### 1. 上下文继承

实现上下文继承，确保子纤程可以访问父纤程的上下文：

```php
use Kode\Fibers\Context\Context;
use Kode\Fibers\Facades\Fiber;

// 设置根上下文
Context::set('app_name', 'My Application');
Context::set('request_id', 'req-123');

// 在父纤程中设置特定上下文
Fiber::run(function () {
    // 这些值会被继承到子纤程
    Context::set('user_id', 456);
    Context::set('session_data', ['theme' => 'dark']);
    
    // 启动子纤程
    Fiber::run(function () {
        // 可以访问所有上下文值
        $appName = Context::get('app_name');        // 'My Application'
        $requestId = Context::get('request_id');    // 'req-123'
        $userId = Context::get('user_id');          // 456
        $sessionData = Context::get('session_data');// ['theme' => 'dark']
        
        // 修改上下文不会影响父纤程
        Context::set('user_id', 789);
    });
    
    // 父纤程中的值保持不变
    $userId = Context::get('user_id'); // 456
});
```

### 2. 上下文传播

在并行任务之间传播上下文：

```php
use Kode\Fibers\Context\Context;
use Kode\Fibers\Facades\Fiber;

// 设置全局上下文
Context::set('app_env', 'production');
Context::set('trace_id', 'trace-' . uniqid());

// 创建多个需要共享上下文的任务
$tasks = [
    function () {
        // 自动继承当前上下文
        $traceId = Context::get('trace_id');
        echo "Task 1 with trace $traceId\n";
        return 'Result 1';
    },
    function () {
        $traceId = Context::get('trace_id');
        echo "Task 2 with trace $traceId\n";
        return 'Result 2';
    }
];

// 并行执行，上下文会自动传播
$results = Fiber::parallel($tasks);
```

## 注解和元编程

利用 PHP 8 注解简化纤程代码：

```php
use Kode\Fibers\Attributes\FiberSafe;
use Kode\Fibers\Attributes\Timeout;
use Kode\Fibers\Attributes\ChannelListener;

// 标记类为纤程安全
#[FiberSafe]
class ApiService
{
    // 设置方法超时时间为5秒
    #[Timeout(5)]
    public function fetchData(int $id): array
    {
        // 远程API调用
        $response = file_get_contents("https://api.example.com/data/$id");
        return json_decode($response, true);
    }
    
    // 监听通道消息
    #[ChannelListener('user.created')]
    public function handleUserCreated(array $userData): void
    {
        // 处理用户创建事件
        $this->sendWelcomeEmail($userData['email']);
    }
}
```

## 性能优化

### 1. 减少内存使用

```php
use Kode\Fibers\Core\FiberPool;

$pool = new FiberPool([
    'size' => 32,
    'enable_memory_tracking' => true,  // 启用内存跟踪
    'max_memory_usage' => 512 * 1024 * 1024,  // 512MB
    'gc_aggressive' => true,           // 启用主动垃圾回收
]);

// 定期检查内存使用情况
Fiber::run(function () use ($pool) {
    while (true) {
        $stats = $pool->getStats();
        if ($stats['memory_usage'] > $pool->getConfig()['max_memory_usage'] * 0.8) {
            // 内存使用过高，触发额外的垃圾回收
            gc_collect_cycles();
            $pool->resetStats();
        }
        Fiber::sleep(10);  // 每10秒检查一次
    }
});
```

### 2. 负载均衡

实现简单的负载均衡策略：

```php
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Facades\Fiber;

// 创建多个专用纤程池
$pools = [
    'io' => new FiberPool(['size' => 16, 'name' => 'io-pool']),
    'cpu' => new FiberPool(['size' => 8, 'name' => 'cpu-pool']),
    'network' => new FiberPool(['size' => 32, 'name' => 'network-pool'])
];

// 负载均衡函数
function runWithLoadBalancer($task, string $type = 'default') {
    global $pools;
    
    // 根据任务类型选择合适的池
    if (isset($pools[$type])) {
        return $pools[$type]->run($task);
    }
    
    // 选择负载最轻的池
    $leastLoaded = null;
    $minLoad = PHP_INT_MAX;
    
    foreach ($pools as $pool) {
        $stats = $pool->getStats();
        $load = $stats['active_fibers'] / $pool->getSize();
        if ($load < $minLoad) {
            $minLoad = $load;
            $leastLoaded = $pool;
        }
    }
    
    return $leastLoaded ? $leastLoaded->run($task) : Fiber::run($task);
}

// 使用负载均衡器运行任务
$ioResult = runWithLoadBalancer(fn() => file_get_contents('large-file.txt'), 'io');
$cpuResult = runWithLoadBalancer(fn() => complexCalculation(), 'cpu');
$networkResult = runWithLoadBalancer(fn() => apiRequest(), 'network');
```

## 监控和日志

### 1. 性能监控

实现简单的性能监控系统：

```php
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Facades\Fiber;

$pool = new FiberPool(['size' => 32]);

// 监控线程
Fiber::run(function () use ($pool) {
    $lastStats = null;
    
    while (true) {
        $stats = $pool->getStats();
        
        // 计算每秒任务数
        $currentTime = microtime(true);
        if ($lastStats && $stats['total_tasks'] > $lastStats['total_tasks']) {
            $taskCount = $stats['total_tasks'] - $lastStats['total_tasks'];
            $timeDiff = $currentTime - $lastStats['time'];
            $tasksPerSecond = $taskCount / $timeDiff;
            
            echo "Tasks per second: $tasksPerSecond\n";
            echo "Active fibers: {$stats['active_fibers']}\n";
            echo "Average execution time: {$stats['avg_execution_time']}ms\n";
            echo "Error rate: {$stats['error_rate']}%\n";
        }
        
        $lastStats = [
            'total_tasks' => $stats['total_tasks'],
            'time' => $currentTime
        ];
        
        Fiber::sleep(1);  // 每秒更新一次
    }
});
```

### 2. 结构化日志

为纤程任务添加结构化日志：

```php
use Kode\Fibers\Facades\Fiber;
use Kode\Fibers\Context\Context;

// 包装任务添加日志
function loggableTask(callable $task, string $taskName) {
    return function () use ($task, $taskName) {
        $startTime = microtime(true);
        $traceId = Context::get('trace_id', uniqid());
        
        // 记录任务开始
        logger()->info('task_started', [
            'task_name' => $taskName,
            'trace_id' => $traceId,
            'fiber_id' => Fiber::getId(),
            'timestamp' => date('c')
        ]);
        
        try {
            // 执行实际任务
            $result = $task();
            
            // 记录任务完成
            $duration = (microtime(true) - $startTime) * 1000;
            logger()->info('task_completed', [
                'task_name' => $taskName,
                'trace_id' => $traceId,
                'duration_ms' => round($duration, 2),
                'success' => true
            ]);
            
            return $result;
        } catch (\Throwable $e) {
            // 记录任务失败
            $duration = (microtime(true) - $startTime) * 1000;
            logger()->error('task_failed', [
                'task_name' => $taskName,
                'trace_id' => $traceId,
                'duration_ms' => round($duration, 2),
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    };
}

// 使用带日志的任务
$results = Fiber::parallel([
    loggableTask(fn() => apiRequest1(), 'api_request_1'),
    loggableTask(fn() => databaseQuery(), 'db_query'),
    loggableTask(fn() => fileOperation(), 'file_operation')
]);
```

## 生产环境最佳实践

### 1. 配置建议

```php
// 生产环境推荐配置
return [
    'default_pool' => [
        'size' => env('FIBER_POOL_SIZE', function() {
            return \Kode\Fibers\Support\CpuInfo::get() * 4;
        }),
        'max_exec_time' => 30,           // 秒
        'gc_interval' => 100,
        'enable_memory_tracking' => true,
        'strict_destruct_check' => PHP_VERSION_ID < 80400,
        'error_handling' => [
            'report_errors' => true,
            'notify_on_critical' => true
        ]
    ],
    'channels' => [
        'default' => ['buffer_size' => 1000],
        'high_priority' => ['buffer_size' => 100],
        'database' => ['buffer_size' => 500]
    ],
    'features' => [
        'auto_suspend_io' => true,
        'enable_monitoring' => true,
        'track_fiber_lifecycle' => true
    ],
    'diagnostics' => [
        'enabled' => true,
        'check_interval' => 300,        // 秒
        'report_path' => storage_path('logs/fiber-diagnostics.log')
    ]
];
```

### 2. 错误处理策略

```php
use Kode\Fibers\Facades\Fiber;
use Kode\Fibers\Exceptions\FiberException;

// 全局错误处理
function setupGlobalErrorHandling() {
    // 注册全局异常处理器
    set_exception_handler(function (\Throwable $e) {
        if ($e instanceof FiberException) {
            // 处理纤程特定异常
            logger()->error('Fiber error', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'fiber_id' => $e->getFiberId() ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
        } else {
            // 处理其他异常
            logger()->error('Unhandled exception', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // 在生产环境中显示友好错误页面
        if (env('APP_ENV') === 'production') {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
            exit;
        }
    });
    
    // 设置错误报告级别
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// 初始化错误处理
setupGlobalErrorHandling();

// 任务中的错误处理
Fiber::run(function () {
    try {
        // 可能出错的操作
        riskyOperation();
    } catch (\RuntimeException $e) {
        // 特定异常处理
        logger()->warning('Expected runtime error', ['message' => $e->getMessage()]);
        // 优雅降级处理
        return fallbackResult();
    } catch (\Throwable $e) {
        // 捕获所有其他异常
        logger()->error('Unexpected error', ['exception' => $e]);
        throw $e;  // 重新抛出以便全局处理器记录
    }
});
```

### 3. 健康检查端点

实现健康检查端点：

```php
use Kode\Fibers\Facades\Fiber;
use Kode\Fibers\Support\Environment;

// 健康检查函数
function healthCheck() {
    try {
        // 检查PHP版本兼容性
        $phpVersionOk = PHP_VERSION_ID >= 80100;
        
        // 检查禁用函数
        $issues = Environment::diagnose();
        $criticalIssues = array_filter($issues, fn($i) => $i['severity'] === 'critical');
        $hasCriticalIssues = count($criticalIssues) > 0;
        
        // 检查数据库连接（如果有）
        $dbConnected = false;
        if (function_exists('checkDatabaseConnection')) {
            try {
                $dbConnected = checkDatabaseConnection();
            } catch (\Exception $e) {
                $dbConnected = false;
            }
        }
        
        // 检查纤程池状态
        $poolStatus = [
            'active_fibers' => 0,
            'total_tasks' => 0
        ];
        
        if (class_exists('\Kode\Fibers\Core\FiberPool') && isset($GLOBALS['main_fiber_pool'])) {
            $pool = $GLOBALS['main_fiber_pool'];
            $stats = $pool->getStats();
            $poolStatus = [
                'active_fibers' => $stats['active_fibers'],
                'total_tasks' => $stats['total_tasks'],
                'error_rate' => $stats['error_rate']
            ];
        }
        
        // 整体状态
        $status = $phpVersionOk && !$hasCriticalIssues ? 'ok' : 'degraded';
        
        return [
            'status' => $status,
            'version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'timestamp' => time(),
            'checks' => [
                'php_version' => ['status' => $phpVersionOk ? 'pass' : 'fail'],
                'environment' => ['status' => $hasCriticalIssues ? 'fail' : 'pass', 'issues' => count($issues)],
                'database' => ['status' => $dbConnected ? 'pass' : 'unknown'],
                'fiber_pool' => $poolStatus
            ]
        ];
    } catch (\Throwable $e) {
        return [
            'status' => 'error',
            'error' => $e->getMessage(),
            'timestamp' => time()
        ];
    }
}

// 创建健康检查端点（示例使用简单的HTTP处理）
function createHealthCheckEndpoint() {
    Fiber::run(function () {
        // 每5秒执行一次自检
        while (true) {
            try {
                $health = healthCheck();
                // 可以将健康状态存储起来供HTTP端点查询
                $GLOBALS['health_status'] = $health;
            } catch (\Exception $e) {
                $GLOBALS['health_status'] = ['status' => 'error', 'error' => $e->getMessage()];
            }
            Fiber::sleep(5);
        }
    });
    
    // HTTP端点示例（根据您的框架调整）
    // 在实际应用中，您应该使用您的框架的路由系统
    if (isset($_GET['health'])) {
        header('Content-Type: application/json');
        echo json_encode($GLOBALS['health_status'] ?? ['status' => 'unknown']);
        exit;
    }
}

// 初始化健康检查
createHealthCheckEndpoint();
```

## 常见模式与反模式

### 最佳实践

1. **使用纤程池而非单个纤程**：在生产环境中，始终使用 `FiberPool` 来管理纤程资源

2. **设置合理的超时**：为所有可能阻塞的操作设置超时时间

3. **使用通道进行通信**：纤程间通信应优先使用 `Channel` 类，避免共享状态

4. **实现错误重试**：对于网络请求等不稳定操作，使用重试机制提高可靠性

5. **监控性能指标**：定期检查纤程池的性能指标，及时发现问题

### 避免这些错误

1. **阻塞操作**：避免在纤程中使用阻塞式I/O操作，如非异步的数据库查询或网络请求

2. **过度使用纤程**：不要为每个小任务都创建纤程，这会增加开销

3. **共享状态**：避免在纤程间共享可变状态，这可能导致竞态条件

4. **无限循环**：确保纤程任务有明确的终止条件

5. **忽略PHP版本限制**：注意PHP 8.4之前析构函数中的限制