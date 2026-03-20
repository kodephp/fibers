<?php

declare(strict_types=1);

/**
 * Kode/Fibers 综合示例
 *
 * 本文件展示所有主要功能的使用方法
 */

require __DIR__ . '/vendor/autoload.php';

use Kode\Fibers\Fibers;
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Core\CircuitBreaker;
use Kode\Fibers\Core\RoundRobinBalancer;
use Kode\Fibers\Rpc\RpcClient;
use Kode\Fibers\Rpc\RpcServer;
use Kode\Fibers\Rpc\WebSocketServer;
use Kode\Fibers\Rpc\WebSocketClient;
use Kode\Fibers\Rpc\GrpcProtocol;
use Kode\Fibers\Transaction\DistributedTransactionManager;
use Kode\Fibers\Transaction\PersistentTransactionManager;
use Kode\Fibers\Transaction\FileTransactionStorage;
use Kode\Fibers\Integration\FrameworkDetector;
use Kode\Fibers\Async\AsyncIO;
use Kode\Fibers\Support\CpuInfo;
use Kode\Fibers\Support\IOuringSupport;

echo "=== Kode/Fibers v3.3 综合示例 ===\n\n";

// 1. 框架检测
echo "1. 框架检测\n";
echo "------------\n";
$info = FrameworkDetector::getRuntimeInfo();
echo "当前框架: {$info['framework']}\n";
echo "Fiber 支持: " . ($info['fiber'] ? '是' : '否') . "\n";
echo "Swoole: " . ($info['swoole'] ? '是' : '否') . "\n";
echo "Swow: " . ($info['swow'] ? '是' : '否') . "\n";
echo "PHP 版本: {$info['php_version']}\n\n";

// 2. CPU 信息
echo "2. 系统信息\n";
echo "------------\n";
echo "CPU 核心数: " . CpuInfo::get() . "\n";
echo "推荐线程池大小: " . CpuInfo::getRecommendedPoolSize() . "\n\n";

// 3. IO_uring 支持
echo "3. IO_uring 支持\n";
echo "------------\n";
$ioStatus = IOuringSupport::getStatus();
echo "Linux 支持: " . ($ioStatus['supported'] ? '是' : '否') . "\n";
echo "扩展已加载: " . ($ioStatus['extension_loaded'] ? '是' : '否') . "\n";
echo "最佳 IO 策略: " . IOuringSupport::getBestIOStrategy() . "\n\n";

// 4. 异步 IO
echo "4. 异步 IO\n";
echo "------------\n";
$async = new AsyncIO();
echo "当前驱动: {$async->getDriver()}\n";
$drivers = AsyncIO::getSupportedDrivers();
echo "支持的驱动: ";
foreach ($drivers as $driver => $supported) {
    echo "{$driver}(" . ($supported ? '是' : '否') . ") ";
}
echo "\n\n";

// 5. Fiber 基础用法
echo "5. Fiber 基础用法\n";
echo "------------\n";
$result = Fibers::go(fn() => 'Hello Fiber!');
echo "同步调用: {$result}\n";

$batchResult = Fibers::batch([1, 2, 3, 4, 5], fn(int $i) => $i * 2, 2);
echo "批量处理: " . implode(', ', $batchResult) . "\n\n";

// 6. 带上下文的并发
echo "6. 上下文并发\n";
echo "------------\n";
$ctxResult = Fibers::withContext(
    ['trace_id' => 'trace-001', 'user_id' => 123],
    fn() => [
        'trace_id' => \Kode\Context\Context::get('trace_id'),
        'user_id' => \Kode\Context\Context::get('user_id')
    ]
);
echo "上下文结果: ";
print_r($ctxResult);
echo "\n";

// 7. 容错批处理
echo "7. 容错批处理\n";
echo "------------\n";
$response = Fibers::resilientBatch(
    ['a' => 1, 'b' => 2, 'c' => 3],
    function (int $item, string $key) {
        if ($key === 'b') {
            throw new RuntimeException('模拟失败');
        }
        return $item * 10;
    },
    ['fail_fast' => false, 'max_retries' => 1]
);
echo "成功结果: ";
print_r($response['results']);
echo "失败键: " . implode(', ', array_keys($response['errors'])) . "\n\n";

// 8. 断路器
echo "8. 断路器\n";
echo "------------\n";
$breaker = new CircuitBreaker(3, 30);

$successCount = 0;
$failCount = 0;

for ($i = 0; $i < 5; $i++) {
    $result = $breaker->execute(
        function () use ($i) {
            if ($i < 3) {
                return "成功 {$i}";
            }
            throw new RuntimeException("失败 {$i}");
        },
        fn() => '降级结果',
        'test-service'
    );
    echo "执行 {$i}: {$result}\n";
    if ($result !== '降级结果') {
        $successCount++;
    } else {
        $failCount++;
    }
}

echo "断路器状态: {$breaker->state()}\n";
echo "成功: {$successCount}, 降级: {$failCount}\n\n";

// 9. 负载均衡
echo "9. 负载均衡\n";
echo "------------\n";
$balancer = new RoundRobinBalancer();

$nodes = ['node-a', 'node-b', 'node-c'];
echo "节点列表: " . implode(', ', $nodes) . "\n";
echo "轮询选择: ";
for ($i = 0; $i < 6; $i++) {
    echo $balancer->nextNode($nodes) . ' ';
}
echo "\n\n";

// 10. RPC 服务器和客户端
echo "10. RPC 通信\n";
echo "------------\n";
$rpcServer = new RpcServer('0.0.0.0', 8080);
$rpcServer->register('user', function ($method, $params) {
    return match ($method) {
        'get' => ['id' => $params['id'] ?? 0, 'name' => 'User'],
        'list' => [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']],
        default => throw new \Kode\Fibers\Rpc\RpcException('Unknown method', -32601),
    };
});
echo "RPC 服务器已创建\n";
echo "服务: " . implode(', ', $rpcServer->getServices()) . "\n";

$rpcClient = RpcClient::json('127.0.0.1', 8080);
echo "JSON-RPC 客户端已创建\n\n";

// 11. WebSocket
echo "11. WebSocket\n";
echo "------------\n";
$wsServer = new WebSocketServer('0.0.0.0', 8081);
$wsServer->register('chat', function ($method, $params) {
    return match ($method) {
        'send' => ['success' => true, 'message_id' => uniqid()],
        'history' => [['id' => 1, 'text' => 'Hello']],
        default => throw new \Kode\Fibers\Rpc\RpcException('Unknown', -32601),
    };
});
echo "WebSocket 服务器已创建\n";
echo "连接数: {$wsServer->getConnectionCount()}\n\n";

// 12. gRPC
echo "12. gRPC\n";
echo "------------\n";
$grpc = GrpcProtocol::createClient('127.0.0.1', 50051, 'user', 'UserService');
echo "gRPC 客户端已创建\n";
echo "地址: 127.0.0.1:50051\n";
echo "包: user, 服务: UserService\n\n";

// 13. 分布式事务 - 2PC
echo "13. 分布式事务 (2PC)\n";
echo "------------\n";
$txn = new DistributedTransactionManager(DistributedTransactionManager::MODE_2PC);
$txn->register('order',
    fn() => print("  [Order] Prepare\n"),
    fn() => print("  [Order] Commit\n"),
    fn() => print("  [Order] Rollback\n")
);
$txn->register('payment',
    fn() => print("  [Payment] Prepare\n"),
    fn() => print("  [Payment] Commit\n"),
    fn() => print("  [Payment] Rollback\n")
);

try {
    $result = $txn->execute(function ($tx) {
        print("  [Business] 执行业务逻辑...\n");
        return ['status' => 'ok'];
    });
    echo "事务成功\n";
} catch (\Throwable $e) {
    echo "事务失败: {$e->getMessage()}\n";
}
echo "\n";

// 14. TCC 模式
echo "14. TCC 补偿型事务\n";
echo "------------\n";
$tcc = new DistributedTransactionManager(DistributedTransactionManager::MODE_TCC);
$tcc->register('stock',
    fn() => print("  [Stock] Try\n"),
    fn() => print("  [Stock] Confirm\n"),
    fn() => print("  [Stock] Cancel\n")
);

$tcc->try('stock', fn() => print("    冻结库存\n"));
$tcc->confirm('stock');
echo "TCC 事务完成\n\n";

// 15. Saga 模式
echo "15. Saga 链式事务\n";
echo "------------\n";
$saga = new DistributedTransactionManager(DistributedTransactionManager::MODE_SAGA);
$saga->addSagaStep('step1',
    fn() => print("  [Saga] 步骤1: 创建订单\n") ?: 'order-123',
    fn() => print("  [Saga] 补偿1: 取消订单\n")
);
$saga->addSagaStep('step2',
    fn() => print("  [Saga] 步骤2: 扣减库存\n") ?: true,
    fn() => print("  [Saga] 补偿2: 恢复库存\n")
);

try {
    $result = $saga->executeSaga();
    echo "Saga 完成\n";
} catch (\Throwable $e) {
    echo "Saga 失败: {$e->getMessage()}\n";
}
echo "\n";

// 16. 持久化事务
echo "16. 持久化事务\n";
echo "------------\n";
$storage = new FileTransactionStorage('/tmp/fibers_demo_' . time());
$ptxn = new PersistentTransactionManager(
    DistributedTransactionManager::MODE_2PC,
    30,
    $storage
);
$ptxn->register('service',
    fn() => print("  [Service] Prepare\n"),
    fn() => print("  [Service] Commit\n"),
    fn() => print("  [Service] Rollback\n")
);

try {
    $ptxn->execute(function ($tx) {
        print("  [Business] 执行持久化事务...\n");
    });
    echo "持久化事务成功\n";
    echo "事务ID: {$ptxn->getTransactionId()}\n";
} catch (\Throwable $e) {
    echo "持久化事务失败: {$e->getMessage()}\n";
}
echo "\n";

echo "=== 示例完成 ===\n";
