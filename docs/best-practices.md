# 最佳实践

本指南汇总了使用 Kode/Fibers 的最佳实践和建议，帮助您在实际项目中充分发挥纤程的优势，避免常见问题。

## 纤程基础最佳实践

### 1. 选择合适的执行方式

- **开发/小规模应用**：可以使用 `Fiber::run()` 快速启动纤程
- **生产环境/大规模应用**：强烈推荐使用 `FiberPool` 管理纤程资源

```php
// 开发环境快速测试
$result = Fiber::run(fn() => doSomeWork());

// 生产环境推荐用法
$pool = new FiberPool(['size' => CpuInfo::get() * 4]);
$result = $pool->execute(fn() => doSomeWork());
```

### 2. 设置合理的池大小

纤程池大小应根据应用类型和服务器资源进行调整：

- **CPU 密集型任务**：池大小设置为 CPU 核心数或略多一些（1-2倍）
- **I/O 密集型任务**：池大小可以设置为 CPU 核心数的 4-8 倍
- **Web 应用**：池大小通常设置为 CPU 核心数的 2-4 倍

```php
use Kode\Fibers\Support\CpuInfo;

// 根据 CPU 核心数动态设置池大小
$cpuCount = CpuInfo::get();
$pool = new FiberPool(['size' => $cpuCount * 4]);
```

### 3. 避免长时间阻塞操作

虽然 Fiber 可以挂起执行，但长时间阻塞的操作仍会影响性能：

```php
// 不好的做法：长时间阻塞操作
Fiber::run(function() {
    // 这会阻塞整个 Fiber，直到命令执行完成
    exec('sleep 60');
});

// 好的做法：将长时间操作拆分为小任务
Fiber::run(function() {
    for ($i = 0; $i < 60; $i++) {
        doSomeSmallWork();
        // 允许其他 Fiber 有机会执行
        \Fiber::suspend();
        usleep(100000); // 短暂睡眠
    }
});
```

### 4. 优先使用非阻塞 I/O

对于 I/O 操作，优先使用非阻塞的实现方式：

```php
// 不好的做法：使用阻塞的 file_get_contents
Fiber::run(function() {
    $data = file_get_contents('https://api.example.com/data');
    processData($data);
});

// 好的做法：使用 Kode/Fibers 提供的 HttpClient
use Kode\Fibers\HttpClient\HttpClient;

$client = new HttpClient();
Fiber::run(function() use ($client) {
    $response = $client->get('https://api.example.com/data');
    processData($response->getBody());
});
```

## 错误处理与调试最佳实践

### 1. 使用 try/catch 捕获纤程内的异常

```php
Fiber::run(function() {
    try {
        // 可能抛出异常的代码
        riskyOperation();
    } catch (\Exception $e) {
        // 处理异常
        logError($e->getMessage());
        // 可以选择返回一个错误状态或重新抛出
        return ['error' => $e->getMessage()];
    }
});
```

### 2. 设置合理的超时时间

始终为任务设置合理的超时时间，避免任务无限期运行：

```php
// 设置全局默认超时
$pool = new FiberPool(['max_exec_time' => 30]);

// 为特定任务设置超时
$pool->execute(fn() => longRunningTask(), 60); // 60秒超时

// 使用 Task 对象设置超时
$task = Task::make(fn() => longRunningTask())->withTimeout(45);
$pool->executeTask($task);
```

### 3. 使用上下文传递调试信息

利用上下文在整个调用链中传递调试信息：

```php
use Kode\Fibers\Context\Context;

// 设置请求 ID 上下文
Context::set('request_id', 'req-'.uniqid());

// 在各个层级的代码中访问请求 ID
function logWithContext($message) {
    $requestId = Context::get('request_id', 'unknown');
    echo "[$requestId] $message\n";
}

// 在纤程中使用
Fiber::run(function() {
    logWithContext("Fiber started");
    doSomeWork();
    logWithContext("Fiber completed");
});
```

### 4. 实现任务重试策略

对于可能失败的操作，实现合理的重试策略：

```php
// 创建带重试功能的任务
$task = RetryableTask::make(
    fn() => unstableApiCall(),
    3, // 最多重试3次
    fn($error, $attempt) => $error instanceof NetworkException, // 只对网络异常重试
    fn($attempt) => min(10, pow(2, $attempt - 1)) // 指数退避：1, 2, 4秒
);

// 执行带重试的任务
$pool->executeTask($task);
```

## 资源管理最佳实践

### 1. 使用 try/finally 确保资源释放

```php
Fiber::run(function() {
    $resource = acquireResource();
    try {
        useResource($resource);
    } finally {
        releaseResource($resource); // 确保资源被释放
    }
});
```

### 2. 避免在析构函数中使用 Fiber::suspend()

在 PHP 8.4 之前，析构函数中不允许调用 `Fiber::suspend()`，即使在 PHP 8.4 及以后版本，也应避免这种做法：

```php
// 不好的做法：在析构函数中调用可能导致挂起的操作
class BadResource {
    public function __destruct() {
        // 这在 PHP < 8.4 中会导致致命错误
        $this->cleanupThatMaySuspend();
    }
}

// 好的做法：提供显式的清理方法
class GoodResource {
    public function __destruct() {
        // 仅执行不会导致挂起的简单清理
        $this->simpleCleanup();
    }
    
    public function cleanup() {
        // 执行可能导致挂起的复杂清理
        $this->complexCleanupThatMaySuspend();
    }
}

// 使用方式
$resource = new GoodResource();
try {
    useResource($resource);
} finally {
    $resource->cleanup(); // 显式调用清理方法
}
```

### 3. 使用连接池管理数据库连接

对于数据库等资源密集型连接，使用连接池可以显著提高性能：

```php
use Kode\Fibers\Support\ConnectionPool;

// 创建数据库连接池
$dbPool = new ConnectionPool(
    function() {
        return new PDO(
            'mysql:host=localhost;dbname=test',
            'username',
            'password',
            [PDO::ATTR_PERSISTENT => true]
        );
    },
    10 // 池大小
);

// 在纤程中使用连接池
$pool = new FiberPool();
$results = $pool->concurrent(array_fill(0, 50, function() use ($dbPool) {
    $db = $dbPool->acquire();
    try {
        $stmt = $db->query('SELECT * FROM users LIMIT 10');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } finally {
        $dbPool->release($db);
    }
}));
```

## 纤程通信最佳实践

### 1. 使用 Channel 进行纤程间通信

对于纤程间的通信，优先使用 Channel 而非共享状态：

```php
use Kode\Fibers\Channel\Channel;

// 创建通道
$channel = Channel::make('data-channel', 10);

// 生产者纤程
Fiber::run(function() use ($channel) {
    for ($i = 0; $i < 100; $i++) {
        $data = generateData($i);
        $channel->push($data);
        usleep(10000); // 模拟工作
    }
    $channel->close(); // 关闭通道表示没有更多数据
});

// 消费者纤程
Fiber::run(function() use ($channel) {
    while (true) {
        $data = $channel->pop(1); // 1秒超时
        if ($data === null && $channel->isClosed()) {
            break; // 通道已关闭且没有更多数据
        }
        if ($data !== null) {
            processData($data);
        }
    }
});
```

### 2. 避免死锁

在使用多个通道或共享资源时，注意避免死锁：

```php
// 可能导致死锁的代码
Fiber::run(function() use ($channelA, $channelB) {
    $dataA = $channelA->pop(); // 等待通道A的数据
    $dataB = $channelB->pop(); // 如果通道B的数据也在等待通道A的数据，就会死锁
    processBothData($dataA, $dataB);
});

// 避免死锁的方法：使用超时或非阻塞操作
Fiber::run(function() use ($channelA, $channelB) {
    $dataA = $channelA->pop(1); // 1秒超时
    if ($dataA === null) {
        // 处理超时情况
        return;
    }
    
    $dataB = $channelB->tryPop(); // 非阻塞尝试获取
    if ($dataB === null) {
        // 处理没有数据的情况，可能将dataA放回通道
        $channelA->push($dataA);
        return;
    }
    
    processBothData($dataA, $dataB);
});
```

### 3. 实现优雅关闭

在应用关闭时，确保所有纤程和通道都能优雅地关闭：

```php
use Kode\Fibers\Facades\Fiber;
use Kode\Fibers\Channel\ChannelRegistry;

// 注册信号处理器
pcntl_signal(SIGINT, function() {
    echo "\nReceived shutdown signal...\n";
    
    // 关闭所有通道
    foreach (ChannelRegistry::all() as $channelName) {
        try {
            $channel = ChannelRegistry::get($channelName);
            if (!$channel->isClosed()) {
                $channel->close();
            }
        } catch (\Exception $e) {
            echo "Error closing channel $channelName: ", $e->getMessage(), "\n";
        }
    }
    
    // 给纤程一些时间完成处理
    echo "Waiting for fibers to finish...\n";
    sleep(2);
    
    echo "Shutdown complete\n";
    exit(0);
});

// 启动主事件循环
while (true) {
    // 主循环逻辑
    usleep(100000);
    
    // 检查是否有需要处理的信号
    pcntl_signal_dispatch();
}
```

## 性能优化最佳实践

### 1. 复用纤程池

创建纤程池的开销相对较大，应尽量复用：

```php
// 不好的做法：每次需要时创建新的池
function processData(array $data) {
    $pool = new FiberPool();
    return $pool->concurrent(array_map(fn($item) => fn() => processItem($item), $data));
}

// 好的做法：复用全局或应用级别的池
class App {
    private static $fiberPool;
    
    public static function getFiberPool() {
        if (!self::$fiberPool) {
            self::$fiberPool = new FiberPool(['size' => CpuInfo::get() * 4]);
        }
        return self::$fiberPool;
    }
}

function processData(array $data) {
    $pool = App::getFiberPool();
    return $pool->concurrent(array_map(fn($item) => fn() => processItem($item), $data));
}
```

### 2. 批量处理任务

对于大量小任务，批量处理比逐个处理更高效：

```php
// 不好的做法：逐个处理大量小任务
function processLotsOfItems(array $items) {
    $pool = App::getFiberPool();
    $results = [];
    foreach ($items as $item) {
        $results[] = $pool->execute(fn() => processItem($item));
    }
    return $results;
}

// 好的做法：批量提交任务
function processLotsOfItems(array $items) {
    $pool = App::getFiberPool();
    $tasks = array_map(fn($item) => fn() => processItem($item), $items);
    return $pool->concurrent($tasks);
}
```

### 3. 使用异步 I/O 扩展

为了获得最佳性能，结合使用异步 I/O 扩展：

```php
// 检查并使用可用的异步 I/O 扩展
function getBestHttpClient() {
    if (extension_loaded('swoole')) {
        return new \Kode\Fibers\HttpClient\Adapters\SwooleAdapter();
    } elseif (extension_loaded('swow')) {
        return new \Kode\Fibers\HttpClient\Adapters\SwowAdapter();
    } else {
        // 回退到标准实现
        return new \Kode\Fibers\HttpClient\HttpClient();
    }
}

// 使用性能最优的 HTTP 客户端
$client = getBestHttpClient();
$response = $client->get('https://api.example.com/data');
```

### 4. 监控纤程池性能

定期监控纤程池的性能指标，以便及时调整配置：

```php
// 定期记录纤程池性能指标
function monitorPool(FiberPool $pool, $interval = 60) {
    Fiber::run(function() use ($pool, $interval) {
        while (true) {
            $stats = $pool->stats();
            
            // 记录性能指标
            $log = sprintf(
                "Pool stats: active=%d, pending=%d, completed=%d, failed=%d, avg_time=%.2fms",
                $stats['active_fibers'],
                $stats['pending_tasks'],
                $stats['completed_tasks'],
                $stats['failed_tasks'],
                $stats['average_execution_time']
            );
            
            logInfo($log);
            
            // 根据性能指标动态调整池大小
            if ($stats['pending_tasks'] > $pool->getSize() * 2 && $pool->getSize() < 128) {
                $newSize = min($pool->getSize() * 2, 128);
                logInfo("Increasing pool size from {$pool->getSize()} to $newSize");
                $pool->resize($newSize);
            } elseif ($stats['active_fibers'] < $pool->getSize() / 2 && $pool->getSize() > 4) {
                $newSize = max($pool->getSize() / 2, 4);
                logInfo("Decreasing pool size from {$pool->getSize()} to $newSize");
                $pool->resize($newSize);
            }
            
            sleep($interval);
        }
    });
}

// 启动监控
$pool = App::getFiberPool();
monitorPool($pool);
```

## 框架集成最佳实践

### 1. 在 HTTP 请求处理中使用纤程

对于 Web 应用，可以在 HTTP 请求处理过程中使用纤程来并发处理多个 I/O 操作：

```php
// Laravel 控制器示例
class UserController extends Controller {
    public function show($id) {
        $pool = App::getFiberPool();
        
        // 并发获取用户相关数据
        [$user, $posts, $comments] = $pool->concurrent([
            fn() => User::find($id),
            fn() => Post::where('user_id', $id)->latest()->take(10)->get(),
            fn() => Comment::where('user_id', $id)->latest()->take(20)->get()
        ]);
        
        return view('user.profile', compact('user', 'posts', 'comments'));
    }
}
```

### 2. 在队列处理中使用纤程

将纤程与队列系统结合使用，可以提高队列处理效率：

```php
// Laravel 队列处理器示例
class ProcessBatchJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    private $items;
    
    public function __construct(array $items) {
        $this->items = $items;
    }
    
    public function handle() {
        // 使用纤程池并发处理队列项
        $pool = app(FiberPool::class);
        $results = $pool->concurrent(array_map(function($item) {
            return fn() => $this->processItem($item);
        }, $this->items));
        
        // 处理结果
        foreach ($results as $index => $result) {
            if ($result['success']) {
                $this->logSuccess($this->items[$index], $result);
            } else {
                $this->logFailure($this->items[$index], $result['error']);
            }
        }
    }
    
    private function processItem($item) {
        // 处理单个队列项
        try {
            // 处理逻辑
            return ['success' => true, 'data' => $processedData];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

### 3. 使用中间件启用全局纤程支持

在框架中使用中间件可以全局启用纤程支持：

```php
// 自定义中间件示例
class EnableFibersMiddleware {
    public function handle($request, Closure $next) {
        // 检查是否已经在纤程中运行
        if (!\Fiber::getCurrent()) {
            // 在纤程中运行后续中间件和控制器
            $response = Fiber::run(fn() => $next($request));
        } else {
            // 已经在纤程中，直接执行
            $response = $next($request);
        }
        
        return $response;
    }
}
```

## 安全最佳实践

### 1. 防止资源耗尽攻击

在处理用户输入时，注意限制并发任务数量，防止资源耗尽攻击：

```php
// 不好的做法：直接使用用户提供的数量创建任务
function processUserRequests($userId, array $requests) {
    $pool = App::getFiberPool();
    $tasks = array_map(fn($req) => fn() => processRequest($userId, $req), $requests);
    return $pool->concurrent($tasks);
}

// 好的做法：限制最大并发任务数量
function processUserRequests($userId, array $requests) {
    // 限制最大任务数量
    $maxTasks = 100;
    if (count($requests) > $maxTasks) {
        throw new \Exception("Too many requests. Maximum is $maxTasks.");
    }
    
    $pool = App::getFiberPool();
    $tasks = array_map(fn($req) => fn() => processRequest($userId, $req), $requests);
    return $pool->concurrent($tasks);
}
```

### 2. 验证用户输入

在将用户输入传递给纤程执行之前，确保进行充分的验证和清理：

```php
function executeUserTask($userId, $taskType, $params) {
    // 验证用户权限
    if (!hasPermission($userId, $taskType)) {
        throw new \Exception("Permission denied");
    }
    
    // 验证和清理参数
    $cleanParams = validateAndSanitizeParams($taskType, $params);
    
    // 执行任务
    return Fiber::run(function() use ($userId, $taskType, $cleanParams) {
        return executeTask($taskType, $cleanParams);
    });
}
```

### 3. 隔离敏感操作

对于包含敏感操作的任务，考虑在隔离的环境中执行：

```php
// 执行敏感操作的包装函数
function executeSensitiveOperation($operation, $params) {
    // 创建一个专用的纤程池用于敏感操作
    $securePool = new FiberPool(['size' => 4]);
    
    // 设置安全上下文
    Context::set('operation_security_level', 'high');
    Context::set('operation_start_time', microtime(true));
    
    try {
        // 执行敏感操作
        $result = $securePool->execute(function() use ($operation, $params) {
            // 额外的安全检查
            securityCheck($operation, $params);
            
            // 执行操作
            return executeOperation($operation, $params);
        });
        
        // 记录成功的敏感操作
        logSensitiveOperation($operation, $params, 'success');
        
        return $result;
    } catch (\Exception $e) {
        // 记录失败的敏感操作
        logSensitiveOperation($operation, $params, 'failure', $e);
        
        throw $e;
    } finally {
        // 清除上下文
        Context::clear();
    }
}
```

## 代码组织最佳实践

### 1. 将纤程逻辑与业务逻辑分离

保持业务逻辑与纤程执行逻辑的分离，提高代码的可维护性和可测试性：

```php
// 业务逻辑类（不依赖纤程）
class UserService {
    public function getUserProfile($userId) {
        $user = $this->getUser($userId);
        $posts = $this->getUserPosts($userId);
        $comments = $this->getUserComments($userId);
        
        return [
            'user' => $user,
            'posts' => $posts,
            'comments' => $comments
        ];
    }
    
    public function getUser($userId) { /* ... */ }
    public function getUserPosts($userId) { /* ... */ }
    public function getUserComments($userId) { /* ... */ }
}

// 纤程执行器（封装纤程逻辑）
class ConcurrentUserService {
    private $userService;
    private $fiberPool;
    
    public function __construct(UserService $userService, FiberPool $fiberPool) {
        $this->userService = $userService;
        $this->fiberPool = $fiberPool;
    }
    
    public function getUserProfileConcurrent($userId) {
        // 使用纤程并发获取数据
        [$user, $posts, $comments] = $this->fiberPool->concurrent([
            fn() => $this->userService->getUser($userId),
            fn() => $this->userService->getUserPosts($userId),
            fn() => $this->userService->getUserComments($userId)
        ]);
        
        return [
            'user' => $user,
            'posts' => $posts,
            'comments' => $comments
        ];
    }
}
```

### 2. 使用依赖注入管理纤程资源

利用依赖注入容器管理纤程池和其他资源，提高代码的灵活性和可测试性：

```php
// Laravel 服务提供者示例
class FibersServiceProvider extends ServiceProvider {
    public function register() {
        // 注册纤程池为单例
        $this->app->singleton(FiberPool::class, function($app) {
            $config = $app->make('config')->get('fibers.pool');
            return new FiberPool([
                'size' => $config['size'] ?? CpuInfo::get() * 4,
                'max_exec_time' => $config['max_exec_time'] ?? 30,
                'gc_interval' => $config['gc_interval'] ?? 100
            ]);
        });
        
        // 注册通道管理器
        $this->app->singleton(ChannelManager::class, function($app) {
            $manager = new ChannelManager();
            
            // 根据配置预创建通道
            $channelsConfig = $app->make('config')->get('fibers.channels', []);
            foreach ($channelsConfig as $name => $config) {
                $manager->create($name, $config['buffer_size'] ?? 0);
            }
            
            return $manager;
        });
        
        // 注册并发服务
        $this->app->singleton(ConcurrentUserService::class, function($app) {
            return new ConcurrentUserService(
                $app->make(UserService::class),
                $app->make(FiberPool::class)
            );
        });
    }
}
```

### 3. 创建可复用的纤程任务类

对于常见的任务模式，创建可复用的任务类：

```php
// 可复用的数据获取任务类
class DataFetchTask implements Runnable {
    private $url;
    private $options;
    private $httpClient;
    
    public function __construct($url, array $options = [], HttpClient $httpClient = null) {
        $this->url = $url;
        $this->options = $options;
        $this->httpClient = $httpClient ?: new HttpClient();
    }
    
    public function run() {
        try {
            $response = $this->httpClient->get($this->url, $this->options);
            return [
                'success' => true,
                'data' => json_decode($response->getBody(), true),
                'status' => $response->getStatusCode()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }
}

// 使用方式
$pool = new FiberPool();
$results = $pool->concurrent([
    new DataFetchTask('https://api.example.com/users'),
    new DataFetchTask('https://api.example.com/products'),
    new DataFetchTask('https://api.example.com/orders')
]);
```

## 下一步

- 查看 [API 参考](api-reference.md) 文档了解完整的 API
- 查看 [框架集成](framework-integration.md) 文档了解如何在不同框架中使用
- 查看 [高级示例](../examples/advanced_example.php) 了解更多实际应用场景