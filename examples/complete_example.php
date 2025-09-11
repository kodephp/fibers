<?php

/**
 * 完整使用示例
 * 
 * 展示如何使用nova/fibers包的各种功能
 */

// 引入Composer自动加载器
require_once __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Channel\Channel;
use Nova\Fibers\Event\EventBus;
use Nova\Fibers\Support\Environment;
use Nova\Fibers\Support\CpuInfo;

// 检查环境是否支持纤程
if (!Environment::checkFiberSupport()) {
    die('当前环境不支持纤程，需要PHP 8.1或更高版本');
}

echo "=== Nova Fibers 完整使用示例 ===\n\n";

// 1. 基础使用：一键启动纤程任务
echo "1. 基础使用：一键启动纤程任务\n";
$result = \Nova\Fibers\Facades\Fiber::run(function() {
    usleep(100000); // 100ms
    return 'Hello from Fiber!';
});
echo "结果: " . $result . "\n\n";

// 2. 使用纤程池执行并发任务
echo "2. 使用纤程池执行并发任务\n";
$pool = new FiberPool([
    'size' => CpuInfo::get() * 2, // 根据CPU核心数设置池大小
    'name' => 'example-pool'
]);

// 创建多个任务
$tasks = [];
for ($i = 1; $i <= 5; $i++) {
    $tasks[] = function() use ($i) {
        usleep(100000); // 100ms
        return "Task $i completed";
    };
}

// 并发执行任务
$results = $pool->concurrent($tasks);
echo "并发任务结果:\n";
foreach ($results as $index => $result) {
    echo "  [$index] $result\n";
}
echo "\n";

// 3. 超时控制示例
echo "3. 超时控制示例\n";
try {
    $timeoutResults = $pool->concurrent([
        function() {
            usleep(50000); // 50ms
            return 'Quick task';
        },
        function() {
            usleep(200000); // 200ms
            return 'Slow task';
        }
    ], 0.1); // 100ms超时
    
    echo "超时控制结果:\n";
    print_r($timeoutResults);
} catch (RuntimeException $e) {
    echo "任务执行超时: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Channel通信示例
echo "4. Channel通信示例\n";
$channel = Channel::make('example-channel', 5);

// 启动生产者纤程
\Nova\Fibers\Facades\Fiber::run(function() use ($channel) {
    for ($i = 1; $i <= 3; $i++) {
        $channel->push("Message $i");
        usleep(50000); // 50ms
    }
    $channel->close();
});

// 消费者
$messages = [];
while (($message = $channel->pop(1)) !== null) { // 1秒超时
    $messages[] = $message;
}
echo "通过Channel接收到的消息:\n";
foreach ($messages as $message) {
    echo "  $message\n";
}
echo "\n";

// 5. EventBus事件发布/订阅示例
echo "5. EventBus事件发布/订阅示例\n";

// 定义一个简单的事件类
class UserRegisteredEvent {
    public $username;
    
    public function __construct($username) {
        $this->username = $username;
    }
}

// 注册事件监听器
EventBus::on(UserRegisteredEvent::class, function($event) {
    echo "监听器收到事件: 用户 {$event->username} 已注册\n";
});

// 触发事件
EventBus::fire(new UserRegisteredEvent('john'));

// 清理事件监听器
EventBus::off(UserRegisteredEvent::class);

echo "\n";

// 6. 环境诊断
echo "6. 环境诊断\n";
$issues = Environment::diagnose();
if (empty($issues)) {
    echo "环境检查通过，无问题发现\n";
} else {
    echo "环境问题:\n";
    foreach ($issues as $issue) {
        echo "  - {$issue['type']}: {$issue['message']}\n";
    }
}
echo "\n";

// 7. CPU信息
echo "7. CPU信息\n";
$cpuCount = CpuInfo::get();
echo "CPU核心数: $cpuCount\n";
echo "推荐的纤程池大小: " . ($cpuCount * 4) . "\n";
echo "\n";

echo "=== 示例执行完成 ===\n";