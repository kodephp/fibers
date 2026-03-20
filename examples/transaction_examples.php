<?php

declare(strict_types=1);

namespace Kode\Fibers\Examples;

require __DIR__ . '/vendor/autoload.php';

use Kode\Fibers\Transaction\DistributedTransactionManager;
use Kode\Fibers\Transaction\PersistentTransactionManager;
use Kode\Fibers\Transaction\FileTransactionStorage;
use Kode\Fibers\Transaction\DatabaseTransactionStorage;

echo "=== 分布式事务示例 ===\n\n";

echo "1. 2PC 两阶段提交\n";
echo "--------------------\n";
$txn = new DistributedTransactionManager(DistributedTransactionManager::MODE_2PC);

$txn->register('order', 
    fn() => print("  [Order] Prepare: 检查库存\n"),
    fn() => print("  [Order] Commit: 创建订单\n"),
    fn() => print("  [Order] Rollback: 取消订单\n")
);

$txn->register('payment',
    fn() => print("  [Payment] Prepare: 验证支付\n"),
    fn() => print("  [Payment] Commit: 确认支付\n"),
    fn() => print("  [Payment] Rollback: 撤销支付\n")
);

$txn->register('inventory',
    fn() => print("  [Inventory] Prepare: 锁定库存\n"),
    fn() => print("  [Inventory] Commit: 扣减库存\n"),
    fn() => print("  [Inventory] Rollback: 释放库存\n")
);

try {
    $result = $txn->execute(function ($tx) {
        print("  [Business] 执行业务逻辑...\n");
        return ['order_id' => 'ORD-' . time(), 'status' => 'ok'];
    });
    echo "事务成功: " . json_encode($result) . "\n";
} catch (\Throwable $e) {
    echo "事务失败: " . $e->getMessage() . "\n";
}

echo "\n事务日志:\n";
foreach ($txn->getLog() as $log) {
    printf("  [%s] %s\n", date('H:i:s', (int)$log['timestamp']), $log['event']);
}
echo "\n";

echo "2. TCC 补偿型事务\n";
echo "--------------------\n";
$tcc = new DistributedTransactionManager(DistributedTransactionManager::MODE_TCC);

$tcc->register('stock',
    fn() => print("  [Stock] Try: 冻结商品\n"),
    fn() => print("  [Stock] Confirm: 确认扣减\n"),
    fn() => print("  [Stock] Cancel: 解冻商品\n")
);

$tcc->register('account',
    fn() => print("  [Account] Try: 冻结余额\n"),
    fn() => print("  [Account] Confirm: 确认扣款\n"),
    fn() => print("  [Account] Cancel: 解冻余额\n")
);

print("执行 Try:\n");
$tcc->try('stock', fn() => print("    冻结商品 A x 2\n"));
$tcc->try('account', fn() => print("    冻结余额 ¥100\n"));

print("\n执行 Confirm:\n");
$tcc->confirm('stock');
$tcc->confirm('account');

echo "\n事务状态:\n";
print_r($tcc->getStatus());
echo "\n";

echo "3. Saga 链式补偿\n";
echo "--------------------\n";
$saga = new DistributedTransactionManager(DistributedTransactionManager::MODE_SAGA);

$saga->addSagaStep('create_order',
    fn() => print("  [Saga] 1. 创建订单\n") ?: 'ORD-' . time(),
    fn() => print("  [Saga] 补偿1: 取消订单\n")
);

$saga->addSagaStep('reserve_stock',
    fn() => print("  [Saga] 2. 预留库存\n") ?: true,
    fn() => print("  [Saga] 补偿2: 释放库存\n")
);

$saga->addSagaStep('process_payment',
    fn() => print("  [Saga] 3. 处理支付\n") ?: 'PAY-' . time(),
    fn() => print("  [Saga] 补偿3: 退款\n")
);

$saga->addSagaStep('ship_order',
    fn() => print("  [Saga] 4. 发货\n") ?: 'SHIP-' . time(),
    fn() => print("  [Saga] 补偿4: 取消发货\n")
);

print("执行 Saga:\n");
try {
    $result = $saga->executeSaga();
    echo "\nSaga 完成! 结果:\n";
    print_r($result);
} catch (\Throwable $e) {
    echo "\nSaga 失败: " . $e->getMessage() . "\n";
    echo "已执行补偿操作\n";
}
echo "\n";

echo "4. 持久化事务\n";
echo "--------------------\n";
$storage = new FileTransactionStorage('/tmp/transactions_' . time());
$ptxn = new PersistentTransactionManager(
    DistributedTransactionManager::MODE_2PC,
    30,
    $storage
);

$ptxn->register('service_a',
    fn() => print("  [ServiceA] Prepare\n"),
    fn() => print("  [ServiceA] Commit\n"),
    fn() => print("  [ServiceA] Rollback\n")
);

$ptxn->register('service_b',
    fn() => print("  [ServiceB] Prepare\n"),
    fn() => print("  [ServiceB] Commit\n"),
    fn() => print("  [ServiceB] Rollback\n")
);

try {
    $result = $ptxn->execute(function ($tx) {
        print("  [Business] 执行持久化事务...\n");
        return ['persistent' => true, 'id' => $tx->getTransactionId()];
    });
    echo "持久化事务成功!\n";
    echo "事务ID: " . $ptxn->getTransactionId() . "\n";
} catch (\Throwable $e) {
    echo "持久化事务失败: " . $e->getMessage() . "\n";
}

echo "\n事务存储路径: /tmp/transactions_" . time() . "\n";
echo "可以从该路径恢复未完成的事务\n\n";

echo "5. 事务状态查询\n";
echo "--------------------\n";
$status = $ptxn->getStatus();
echo "事务ID: {$status['transaction_id']}\n";
echo "事务模式: {$status['mode']}\n";
echo "已提交: " . ($status['committed'] ? '是' : '否') . "\n";
echo "已回滚: " . ($status['rolled_back'] ? '是' : '否') . "\n";
echo "参与者:\n";
foreach ($status['participants'] as $name => $info) {
    $s = is_array($info) ? ($info['status'] ?? 'unknown') : $info;
    echo "  - {$name}: {$s}\n";
}

echo "\n=== 示例完成 ===\n";
