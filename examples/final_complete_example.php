<?php

/**
 * 最终完整示例
 * 
 * 展示nova/fibers包的所有主要功能
 */

// 引入Composer自动加载器
require_once __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Channel\Channel;
use Nova\Fibers\Event\EventBus;
use Nova\Fibers\Support\Environment;
use Nova\Fibers\Support\CpuInfo;

echo "=== Nova Fibers 完整功能演示 ===\n\n";

// 1. 环境检查
echo "1. 环境检查...\n";
if (!Environment::checkFiberSupport()) {
    die('当前环境不支持纤程，需要PHP 8.1或更高版本');
}

echo "   ✓ PHP版本: " . PHP_VERSION . "\n";
echo "   ✓ 纤程支持: 可用\n";
echo "   ✓ CPU核心数: " . CpuInfo::get() . "\n\n";

// 2. 基础纤程使用
echo "2. 基础纤程使用...\n";
$result = \Nova\Fibers\Facades\Fiber::run(function() {
    usleep(100000); // 100ms
    return "Hello from Fiber!";
});
echo "   结果: " . $result . "\n\n";

// 3. 纤程池并发任务
echo "3. 纤程池并发任务...\n";
$pool = new FiberPool([
    'size' => 4,
    'name' => 'demo-pool'
]);

$tasks = [];
for ($i = 1; $i <= 3; $i++) {
    $tasks[] = function() use ($i) {
        usleep(100000); // 100ms
        return "Task $i completed";
    };
}

$results = $pool->concurrent($tasks);
foreach ($results as $result) {
    echo "   " . $result . "\n";
}
echo "\n";

// 4. 超时控制
echo "4. 超时控制...\n";
try {
    $results = $pool->concurrent([
        function() {
            usleep(200000); // 200ms
            return 'Slow task';
        }
    ], 0.1); // 100ms timeout
    
    echo "   结果: " . json_encode($results) . "\n";
} catch (RuntimeException $e) {
    echo "   超时异常: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Channel通信
echo "5. Channel通信...\n";
$channel = Channel::make('demo-channel', 2);

// 生产者纤程
$fiber = new Fiber(function() use ($channel) {
    for ($i = 1; $i <= 3; $i++) {
        $channel->push("Message $i");
        usleep(50000); // 50ms
    }
    $channel->close();
});
$fiber->start();

// 消费者
$messages = [];
while (($msg = $channel->pop(1)) !== null) { // 1秒超时
    $messages[] = $msg;
}
echo "   接收到的消息: " . implode(', ', $messages) . "\n\n";

// 6. EventBus事件发布/订阅
echo "6. EventBus事件发布/订阅...\n";

// 定义事件类
class UserRegisteredEvent {
    public $userId;
    
    public function __construct($userId) {
        $this->userId = $userId;
    }
}

// 注册监听器
EventBus::on(UserRegisteredEvent::class, function($event) {
    echo "   监听到用户注册事件: 用户ID {$event->userId}\n";
});

// 发布事件
EventBus::fire(new UserRegisteredEvent(12345));

// 清理监听器
EventBus::off(UserRegisteredEvent::class);
echo "\n";

// 7. 环境诊断
echo "7. 环境诊断...\n";
$issues = Environment::diagnose();
if (empty($issues)) {
    echo "   ✓ 环境检查通过，无问题发现\n";
} else {
    foreach ($issues as $issue) {
        echo "   ⚠️ {$issue['type']}: {$issue['message']}\n";
    }
}
echo "\n";

// 8. CPU信息
echo "8. CPU信息...\n";
echo "   CPU核心数: " . CpuInfo::get() . "\n\n";

echo "=== 演示完成 ===\n";