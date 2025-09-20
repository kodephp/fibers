<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Facades\FiberManager;
use Nova\Fibers\Core\EnhancedEventLoop;
use Nova\Fibers\Context\EnhancedContextManager as ContextManager;
use Nova\Fibers\Scheduler\EnhancedDistributedScheduler;
use Nova\Fibers\Event\EnhancedEventBus;
use Nova\Fibers\Core\AutomaticTimeoutHandler;
use Nova\Fibers\Attributes\Timeout;
use Nova\Fibers\Attributes\Retry;
use Nova\Fibers\Attributes\Backoff;

/**
 * 示例服务类
 */
class ExampleService
{
    /**
     * 带超时控制的方法
     * 
     * @Timeout(2)
     */
    public function fetchData()
    {
        // 模拟耗时操作
        sleep(1);
        return ['data' => 'example data', 'time' => time()];
    }
    
    /**
     * 带重试和退避策略的方法
     * 
     * @Retry(maxAttempts=3, delay=1000)
     * @Backoff(strategy="exponential", baseDelay=1000, multiplier=2.0)
     */
    public function unreliableOperation()
    {
        // 模拟可能失败的操作
        if (rand(0, 1) === 1) {
            throw new \Exception("Random failure");
        }
        
        return "Success";
    }
}

// 1. 增强的上下文管理示例
echo "=== 增强的上下文管理示例 ===\n";

// 设置上下文
ContextManager::withValue('user_id', 12345);
ContextManager::withValue('session_id', 'sess_abc123');
ContextManager::withValue('request_id', 'req_xyz789');

echo "User ID: " . ContextManager::getValue('user_id') . "\n";
echo "Session ID: " . ContextManager::getValue('session_id') . "\n";

// 设置上下文权限
ContextManager::setPermission('sensitive_data', false);

try {
    ContextManager::withValue('sensitive_data', 'secret', true);
} catch (\RuntimeException $e) {
    echo "权限控制示例: " . $e->getMessage() . "\n";
}

// 2. 增强的事件循环示例
echo "\n=== 增强的事件循环示例 ===\n";

// 延迟执行
$deferId = EnhancedEventLoop::defer(function () {
    echo "延迟执行的任务\n";
}, 'example_defer');

// 定时执行
$delayId = EnhancedEventLoop::delay(1.0, function () {
    echo "1秒后执行的任务\n";
}, 'example_delay');

// 重复执行
$repeatId = EnhancedEventLoop::repeat(0.5, function () {
    static $count = 0;
    echo "重复执行的任务 #" . (++$count) . "\n";
    
    if ($count >= 3) {
        EnhancedEventLoop::cancel('repeat_task');
        echo "取消重复任务\n";
    }
}, 'repeat_task');

// 获取事件循环状态
$status = EnhancedEventLoop::getStatus();
echo "事件循环状态: " . json_encode($status) . "\n";

// 3. 增强的分布式调度示例
echo "\n=== 增强的分布式调度示例 ===\n";

$scheduler = new EnhancedDistributedScheduler(null, [
    'cluster_nodes' => ['node1.example.com', 'node2.example.com', 'node3.example.com']
]);

$taskId = $scheduler->submitToCluster(function () {
    return "Task executed on cluster node";
});

echo "提交集群任务，ID: {$taskId}\n";

// 4. 增强的发布/订阅示例
echo "\n=== 增强的发布/订阅示例 ===\n";

// 订阅事件（带权重）
EnhancedEventBus::on('user.registered', function ($event) {
    echo "处理用户注册事件 (低优先级)\n";
}, 0);

EnhancedEventBus::on('user.registered', function ($event) {
    echo "发送欢迎邮件 (高优先级)\n";
}, 10);

// 发布事件
EnhancedEventBus::fire((object)[
    'type' => 'user.registered',
    'userId' => 12345,
    'email' => 'user@example.com'
]);

// 获取事件统计
$stats = EnhancedEventBus::getStats();
echo "事件统计: " . json_encode($stats) . "\n";

// 5. 自动超时控制示例
echo "\n=== 自动超时控制示例 ===\n";

$service = new ExampleService();

try {
    $result = AutomaticTimeoutHandler::applyTimeout($service, 'fetchData');
    echo "获取数据成功: " . json_encode($result) . "\n";
} catch (\RuntimeException $e) {
    echo "获取数据超时: " . $e->getMessage() . "\n";
}

// 6. 重试和退避策略示例
echo "\n=== 重试和退避策略示例 ===\n";

try {
    $result = AutomaticTimeoutHandler::applyTimeout($service, 'unreliableOperation');
    echo "不可靠操作成功: " . $result . "\n";
} catch (\Exception $e) {
    echo "不可靠操作失败: " . $e->getMessage() . "\n";
}

echo "\n示例执行完成\n";