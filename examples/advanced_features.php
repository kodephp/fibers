<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Kode\Fibers\Rpc\RpcClient;
use Kode\Fibers\Rpc\RpcServer;
use Kode\Fibers\Rpc\RpcException;
use Kode\Fibers\Rpc\Protocols\JsonRpcProtocol;
use Kode\Fibers\Rpc\Protocols\MessagePackProtocol;
use Kode\Fibers\Transaction\DistributedTransactionManager;
use Kode\Fibers\Integration\FrameworkDetector;
use Kode\Fibers\Integration\IntegrationManager;
use Kode\Fibers\Async\AsyncIO;

echo "=== Kode/Fibers 3.1 示例 ===\n\n";

echo "1. 框架检测\n";
echo "------------\n";
$info = FrameworkDetector::getRuntimeInfo();
printf("当前框架: %s\n", $info['framework']);
printf("Fiber 支持: %s\n", $info['fiber'] ? '是' : '否');
printf("Swoole 支持: %s\n", $info['swoole'] ? '是' : '否');
printf("PHP 版本: %s\n", $info['php_version']);
echo "\n";

echo "2. 异步 IO\n";
echo "------------\n";
$async = new AsyncIO();
printf("当前驱动: %s\n", $async->getDriver());

$drivers = AsyncIO::getSupportedDrivers();
echo "支持的驱动: ";
foreach ($drivers as $driver => $supported) {
    printf("%s(%s) ", $driver, $supported ? '是' : '否');
}
echo "\n\n";

echo "3. RPC 客户端\n";
echo "------------\n";
$client = RpcClient::json('127.0.0.1', 8080);

// 如果服务器运行中，可以这样调用：
// try {
//     $result = $client->call('user.get', ['id' => 1]);
//     print_r($result);
// } catch (RpcException $e) {
//     echo "RPC 错误: " . $e->getMessage() . "\n";
// }

// 批量调用示例
// $results = $client->batchCall([
//     ['user.get', ['id' => 1]],
//     ['user.get', ['id' => 2]],
// ]);
echo "RPC 客户端已创建 (JSON 协议)\n\n";

echo "4. RPC 服务器\n";
echo "------------\n";
$server = new RpcServer('0.0.0.0', 8080);

// 注册服务
$server->register('user', function ($method, $params) {
    return match ($method) {
        'get' => ['id' => $params['id'] ?? 0, 'name' => 'User'],
        'list' => [['id' => 1, 'name' => 'User1'], ['id' => 2, 'name' => 'User2']],
        default => throw new RpcException('Unknown method', -32601),
    };
});

echo "RPC 服务器已创建\n";
echo "服务列表: " . implode(', ', $server->getServices()) . "\n\n";

echo "5. 分布式事务 (2PC 模式)\n";
echo "------------\n";
$txn = new DistributedTransactionManager(DistributedTransactionManager::MODE_2PC);

// 注册参与者
$txn->register('order', function () { echo "  [Order] Prepare\n"; }, function () { echo "  [Order] Commit\n"; }, function () { echo "  [Order] Rollback\n"; });
$txn->register('payment', function () { echo "  [Payment] Prepare\n"; }, function () { echo "  [Payment] Commit\n"; }, function () { echo "  [Payment] Rollback\n"; });
$txn->register('inventory', function () { echo "  [Inventory] Prepare\n"; }, function () { echo "  [Inventory] Commit\n"; }, function () { echo "  [Inventory] Rollback\n"; });

// 执行事务
try {
    $result = $txn->execute(function ($tx) {
        echo "  执行业务逻辑...\n";
        return ['order_id' => 'ORD123', 'status' => 'completed'];
    });
    echo "  事务结果: ";
    print_r($result);
} catch (Throwable $e) {
    echo "  事务失败: " . $e->getMessage() . "\n";
}

echo "\n事务日志:\n";
foreach ($txn->getLog() as $log) {
    printf("  [%s] %s\n", date('H:i:s', (int)$log['timestamp']), $log['event']);
}
echo "\n";

echo "6. TCC 模式示例\n";
echo "------------\n";
$tcc = new DistributedTransactionManager(DistributedTransactionManager::MODE_TCC);

$tcc->register('stock', function () { echo "  [Stock] Try\n"; }, function () { echo "  [Stock] Confirm\n"; }, function () { echo "  [Stock] Cancel\n"; });
$tcc->register('account', function () { echo "  [Account] Try\n"; }, function () { echo "  [Account] Confirm\n"; }, function () { echo "  [Account] Cancel\n"; });

$tcc->try('stock', function () { echo "    预留库存...\n"; });
$tcc->try('account', function () { echo "    冻结资金...\n"; });
$tcc->confirm('stock');
$tcc->confirm('account');

echo "\n事务状态:\n";
print_r($tcc->getStatus());
echo "\n";

echo "7. Saga 模式示例\n";
echo "------------\n";
$saga = new DistributedTransactionManager(DistributedTransactionManager::MODE_SAGA);

$saga->addSagaStep('step1', function () { echo "  [Saga] 步骤1: 创建订单\n"; return 'order_123'; }, function () { echo "  [Saga] 补偿: 取消订单\n"; });
$saga->addSagaStep('step2', function () { echo "  [Saga] 步骤2: 扣减库存\n"; }, function () { echo "  [Saga] 补偿: 恢复库存\n"; });
$saga->addSagaStep('step3', function () { echo "  [Saga] 步骤3: 发送通知\n"; }, function () { echo "  [Saga] 补偿: 撤回通知\n"; });

try {
    $result = $saga->executeSaga();
    echo "  Saga 完成\n";
} catch (Throwable $e) {
    echo "  Saga 失败: " . $e->getMessage() . "\n";
    echo "  已执行补偿操作\n";
}
echo "\n";

echo "=== 示例完成 ===\n";
