# LocalScheduler 使用指南

## 概述

LocalScheduler 是 nova/fibers 包中分布式调度器接口的一个本地实现。它提供了任务提交、结果获取、任务取消和状态查询等功能，是构建分布式任务调度系统的基础组件。

## 安装和设置

LocalScheduler 是 nova/fibers 包的一部分，安装包后即可使用：

```bash
composer require nova/fibers
```

## 基本使用

### 初始化

创建 LocalScheduler 实例非常简单：

```php
use Nova\Fibers\Scheduler\LocalScheduler;

// 使用默认配置创建调度器
$scheduler = new LocalScheduler();

// 或者指定 Fiber 池配置
$scheduler = new LocalScheduler([
    'size' => 8,
    'timeout' => 30,
    'max_retries' => 3
]);
```

### 提交任务

使用 `submit` 方法提交任务到调度器：

```php
use Nova\Fibers\Scheduler\LocalScheduler;

$scheduler = new LocalScheduler();

// 提交一个简单任务
$taskId = $scheduler->submit(function() {
    return "Task result";
});

echo "Task ID: " . $taskId . "\n";
```

### 获取任务结果

使用 `getResult` 方法获取任务执行结果：

```php
use Nova\Fibers\Scheduler\LocalScheduler;

$scheduler = new LocalScheduler();

$taskId = $scheduler->submit(function() {
    // 模拟一些工作
    sleep(1);
    return "Task completed";
});

// 获取任务结果（阻塞直到任务完成）
try {
    $result = $scheduler->getResult($taskId);
    echo "Result: " . $result . "\n";
} catch (Exception $e) {
    echo "Task failed: " . $e->getMessage() . "\n";
}
```

可以指定超时时间：

```php
use Nova\Fibers\Scheduler\LocalScheduler;

$scheduler = new LocalScheduler();

$taskId = $scheduler->submit(function() {
    sleep(5); // 长时间运行的任务
    return "Task completed";
});

try {
    // 等待最多2秒
    $result = $scheduler->getResult($taskId, 2.0);
    echo "Result: " . $result . "\n";
} catch (Exception $e) {
    echo "Task timed out or failed: " . $e->getMessage() . "\n";
}
```

### 查询任务状态

使用 `getStatus` 方法查询任务状态：

```php
use Nova\Fibers\Scheduler\LocalScheduler;

$scheduler = new LocalScheduler();

$taskId = $scheduler->submit(function() {
    sleep(2);
    return "Task completed";
});

// 查询任务状态
$status = $scheduler->getStatus($taskId);
echo "Task status: " . $status . "\n"; // 可能输出: pending, running, completed, failed, cancelled
```

### 取消任务

使用 `cancel` 方法取消任务：

```php
use Nova\Fibers\Scheduler\LocalScheduler;

$scheduler = new LocalScheduler();

$taskId = $scheduler->submit(function() {
    sleep(10);
    return "Task completed";
});

// 取消任务
if ($scheduler->cancel($taskId)) {
    echo "Task cancelled successfully\n";
} else {
    echo "Failed to cancel task\n";
}
```

### 获取集群信息

使用 `getClusterInfo` 方法获取集群节点信息：

```php
use Nova\Fibers\Scheduler\LocalScheduler;

$scheduler = new LocalScheduler();

$clusterInfo = $scheduler->getClusterInfo();
print_r($clusterInfo);
```

## 高级功能

### 使用上下文

LocalScheduler 支持在任务执行时传递上下文信息：

```php
use Nova\Fibers\Scheduler\LocalScheduler;
use Nova\Fibers\Context\Context;

$scheduler = new LocalScheduler();

$context = new Context('task_context');
$context = $context->withValue('user_id', 123);
$context = $context->withValue('request_id', 'req_abc123');

$taskId = $scheduler->submit(function() {
    // 在任务中可以访问上下文信息
    // 这里只是一个示例，实际使用时需要通过 ContextManager 获取上下文
    return "Task with context";
}, $context);

$result = $scheduler->getResult($taskId);
echo "Result: " . $result . "\n";
```

### 错误处理

LocalScheduler 提供了完善的错误处理机制：

```php
use Nova\Fibers\Scheduler\LocalScheduler;

$scheduler = new LocalScheduler();

// 提交一个会抛出异常的任务
$taskId = $scheduler->submit(function() {
    throw new Exception("Task failed");
    return "This will not be reached";
});

try {
    $result = $scheduler->getResult($taskId);
    echo "Result: " . $result . "\n";
} catch (Exception $e) {
    echo "Task failed with error: " . $e->getMessage() . "\n";
}

// 检查任务状态
$status = $scheduler->getStatus($taskId);
echo "Task status: " . $status . "\n"; // 输出: failed
```

## 实际应用示例

### 批量任务处理

```php
use Nova\Fibers\Scheduler\LocalScheduler;

class BatchProcessor 
{
    private LocalScheduler $scheduler;
    
    public function __construct() 
    {
        $this->scheduler = new LocalScheduler([
            'size' => 16, // 增加并发数
            'timeout' => 60
        ]);
    }
    
    public function processBatch(array $items): array 
    {
        $taskIds = [];
        
        // 提交所有任务
        foreach ($items as $item) {
            $taskId = $this->scheduler->submit(function() use ($item) {
                // 模拟处理过程
                usleep(rand(100000, 500000)); // 0.1-0.5秒
                
                // 模拟可能的失败
                if (rand(1, 10) <= 1) { // 10% 失败率
                    throw new Exception("Processing failed for item: " . $item);
                }
                
                return "Processed: " . $item;
            });
            
            $taskIds[$taskId] = $item;
        }
        
        // 收集结果
        $results = [];
        foreach ($taskIds as $taskId => $item) {
            try {
                $result = $this->scheduler->getResult($taskId, 5.0); // 5秒超时
                $results[$item] = $result;
            } catch (Exception $e) {
                $results[$item] = "Error: " . $e->getMessage();
            }
        }
        
        return $results;
    }
}

// 使用示例
$processor = new BatchProcessor();
$items = ['item1', 'item2', 'item3', 'item4', 'item5'];

$results = $processor->processBatch($items);

foreach ($results as $item => $result) {
    echo "$item: $result\n";
}
```

### 异步 HTTP 请求

```php
use Nova\Fibers\Scheduler\LocalScheduler;

class AsyncHttpClient 
{
    private LocalScheduler $scheduler;
    
    public function __construct() 
    {
        $this->scheduler = new LocalScheduler([
            'size' => 8,
            'timeout' => 30
        ]);
    }
    
    public function get(string $url): string 
    {
        $taskId = $this->scheduler->submit(function() use ($url) {
            // 使用 curl 执行异步 HTTP 请求
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL error: " . $error);
            }
            
            if ($httpCode >= 400) {
                throw new Exception("HTTP error: " . $httpCode);
            }
            
            return $response;
        });
        
        return $this->scheduler->getResult($taskId, 15.0); // 15秒超时
    }
    
    public function getMultiple(array $urls): array 
    {
        $taskIds = [];
        
        // 提交所有请求
        foreach ($urls as $url) {
            $taskId = $this->scheduler->submit(function() use ($url) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    throw new Exception("cURL error for $url: " . $error);
                }
                
                if ($httpCode >= 400) {
                    throw new Exception("HTTP error for $url: " . $httpCode);
                }
                
                return [
                    'url' => $url,
                    'response' => $response,
                    'http_code' => $httpCode
                ];
            });
            
            $taskIds[$taskId] = $url;
        }
        
        // 收集结果
        $results = [];
        foreach ($taskIds as $taskId => $url) {
            try {
                $result = $this->scheduler->getResult($taskId, 15.0);
                $results[$url] = $result;
            } catch (Exception $e) {
                $results[$url] = [
                    'url' => $url,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}

// 使用示例
$client = new AsyncHttpClient();

// 单个请求
try {
    $response = $client->get('https://httpbin.org/json');
    echo "Response length: " . strlen($response) . " bytes\n";
} catch (Exception $e) {
    echo "Request failed: " . $e->getMessage() . "\n";
}

// 多个并发请求
$urls = [
    'https://httpbin.org/json',
    'https://httpbin.org/uuid',
    'https://httpbin.org/user-agent'
];

$results = $client->getMultiple($urls);

foreach ($results as $url => $result) {
    if (isset($result['error'])) {
        echo "$url: Error - " . $result['error'] . "\n";
    } else {
        echo "$url: " . strlen($result['response']) . " bytes\n";
    }
}
```

## 最佳实践

1. **合理配置并发数**：根据系统资源和任务特性合理设置 Fiber 池大小。

2. **设置合适的超时时间**：为任务执行和结果获取设置合理的超时时间，避免无限等待。

3. **错误处理**：始终使用 try-catch 块处理 `getResult` 方法可能抛出的异常。

4. **资源管理**：及时处理已完成任务的结果，避免内存泄漏。

5. **状态检查**：在关键节点检查任务状态，确保任务按预期执行。

6. **测试**：编写测试用例验证各种场景下的行为，包括正常执行、超时、异常等。

## 注意事项

1. `getResult` 方法是阻塞的，会等待任务完成或超时。

2. 任务一旦提交就无法真正取消，`cancel` 方法只是标记任务为已取消。

3. 任务状态包括：pending（待处理）、running（运行中）、completed（已完成）、failed（失败）、cancelled（已取消）、unknown（未知）。

4. LocalScheduler 是本地实现，不支持真正的分布式任务调度。如需分布式功能，需要实现 `DistributedSchedulerInterface` 接口。