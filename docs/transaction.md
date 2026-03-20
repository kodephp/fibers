# 分布式事务指南

## 概述

`kode/fibers` 提供三种分布式事务模式：

| 模式 | 类 | 适用场景 |
|------|-----|---------|
| 2PC 两阶段提交 | `DistributedTransactionManager` | 强一致性，短事务 |
| TCC 补偿型 | `DistributedTransactionManager` (MODE_TCC) | 跨服务，长事务 |
| Saga 链式补偿 | `DistributedTransactionManager` (MODE_SAGA) | 松耦合，长流程 |

## 2PC 两阶段提交

### 原理

```
        事务管理器
            │
    ┌───────┴───────┐
    │               │
Prepare           Commit
    │               │
    ▼               ▼
 参与者1         参与者1
 参与者2         参与者2
 参与者3         参与者3
```

1. **Prepare 阶段**：所有参与者执行预提交
2. **Commit 阶段**：所有参与者提交或回滚

### 示例

```php
use Kode\Fibers\Transaction\DistributedTransactionManager;

$txn = new DistributedTransactionManager(DistributedTransactionManager::MODE_2PC);

// 注册订单服务
$txn->register(
    'order',
    function () {
        echo "  [Order] 预检查库存...\n";
        return ['order_id' => 'ORD123'];
    },
    function () {
        echo "  [Order] 确认订单\n";
    },
    function () {
        echo "  [Order] 取消订单\n";
    }
);

// 注册支付服务
$txn->register(
    'payment',
    function () {
        echo "  [Payment] 预授权...\n";
        return ['payment_id' => 'PAY456'];
    },
    function () {
        echo "  [Payment] 确认支付\n";
    },
    function () {
        echo "  [Payment] 释放授权\n";
    }
);

// 注册库存服务
$txn->register(
    'inventory',
    function () {
        echo "  [Inventory] 锁定库存...\n";
    },
    function () {
        echo "  [Inventory] 确认扣减\n";
    },
    function () {
        echo "  [Inventory] 释放库存\n";
    }
);

// 执行事务
try {
    $result = $txn->execute(function ($tx) {
        echo "  执行业务逻辑...\n";
        return [
            'order_id' => 'ORD123',
            'payment_id' => 'PAY456',
            'status' => 'completed'
        ];
    });
    
    echo "事务成功: ";
    print_r($result);
} catch (\Throwable $e) {
    echo "事务失败: " . $e->getMessage() . "\n";
}

// 查看事务日志
echo "\n事务日志:\n";
foreach ($txn->getLog() as $log) {
    printf("  [%s] %s\n", date('H:i:s', (int)$log['timestamp']), $log['event']);
}
```

## TCC 补偿型事务

### 原理

```
Try     →  Confirm  →  完成
  │           │
  └────┬──────┘
       ▼
     Cancel
```

1. **Try**：预留资源（冻结/锁定）
2. **Confirm**：确认使用预留资源
3. **Cancel**：释放预留资源

### 示例

```php
use Kode\Fibers\Transaction\DistributedTransactionManager;

$tcc = new DistributedTransactionManager(DistributedTransactionManager::MODE_TCC);

// 注册账户服务
$tcc->register('account', 
    function () { echo "  [Account] 冻结资金\n"; return true; },
    function () { echo "  [Account] 确认转账\n"; },
    function () { echo "  [Account] 解冻资金\n"; }
);

// 注册库存服务
$tcc->register('stock',
    function () { echo "  [Stock] 预留库存\n"; return true; },
    function () { echo "  [Stock] 确认扣减\n"; },
    function () { echo "  [Stock] 归还库存\n"; }
);

// 注册物流服务
$tcc->register('logistics',
    function () { echo "  [Logistics] 创建运单\n"; return true; },
    function () { echo "  [Logistics] 确认发货\n"; },
    function () { echo "  [Logistics] 取消运单\n"; }
);

// 执行 TCC
echo "=== Try 阶段 ===\n";
$tcc->try('account', fn() => echo "    冻结用户余额 $100...\n");
$tcc->try('stock', fn() => echo "    锁定商品 2 件...\n");
$tcc->try('logistics', fn() => echo "    分配配送运单...\n");

echo "\n=== Confirm 阶段 ===\n";
$tcc->confirm('account');
$tcc->confirm('stock');
$tcc->confirm('logistics');

echo "\n事务状态:\n";
print_r($tcc->getStatus());
```

## Saga 模式

### 原理

```
步骤1 → 步骤2 → 步骤3 → 完成
    ↑       ↑       ↑
    └── 补偿1 ← 补偿2 ← 补偿3
```

每个步骤有对应的补偿操作，失败时逆序执行补偿。

### 示例

```php
use Kode\Fibers\Transaction\DistributedTransactionManager;

$saga = new DistributedTransactionManager(DistributedTransactionManager::MODE_SAGA);

// 添加 Saga 步骤
$saga->addSagaStep('create_order',
    function () {
        echo "  [Saga] 步骤1: 创建订单\n";
        return ['order_id' => 'ORD-' . time()];
    },
    function () {
        echo "  [Saga] 补偿1: 取消订单\n";
    }
);

$saga->addSagaStep('reserve_inventory',
    function () {
        echo "  [Saga] 步骤2: 预留库存\n";
        return ['inventory_reserved' => true];
    },
    function () {
        echo "  [Saga] 补偿2: 释放库存\n";
    }
);

$saga->addSagaStep('process_payment',
    function () {
        echo "  [Saga] 步骤3: 处理支付\n";
        return ['payment_id' => 'PAY-' . time()];
    },
    function () {
        echo "  [Saga] 补偿3: 退款\n";
    }
);

$saga->addSagaStep('schedule_delivery',
    function () {
        echo "  [Saga] 步骤4: 安排发货\n";
        return ['delivery_id' => 'DEL-' . time()];
    },
    function () {
        echo "  [Saga] 补偿4: 取消发货\n";
    }
);

// 执行 Saga
echo "=== 执行 Saga ===\n";
try {
    $result = $saga->executeSaga();
    echo "\nSaga 完成! 结果: ";
    print_r($result);
} catch (\Throwable $e) {
    echo "\nSaga 失败: " . $e->getMessage() . "\n";
    echo "已执行补偿操作\n";
}
```

## 持久化事务

### 文件存储

```php
use Kode\Fibers\Transaction\PersistentTransactionManager;
use Kode\Fibers\Transaction\FileTransactionStorage;

// 使用文件存储
$storage = new FileTransactionStorage('/var/data/transactions');
$txn = new PersistentTransactionManager(
    DistributedTransactionManager::MODE_2PC,
    30,
    $storage
);

// 注册参与者...

// 事务自动持久化
$txn->execute(function ($tx) {
    // 业务逻辑
});
```

### 数据库存储

```php
use Kode\Fibers\Transaction\PersistentTransactionManager;
use Kode\Fibers\Transaction\DatabaseTransactionStorage;

// 使用数据库存储
$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
$storage = new DatabaseTransactionStorage($pdo, 'distributed_transactions');

$txn = new PersistentTransactionManager(
    DistributedTransactionManager::MODE_2PC,
    30,
    $storage
);
```

### 故障恢复

```php
use Kode\Fibers\Transaction\PersistentTransactionManager;
use Kode\Fibers\Transaction\FileTransactionStorage;

$storage = new FileTransactionStorage('/var/data/transactions');
$txn = new PersistentTransactionManager(
    DistributedTransactionManager::MODE_2PC,
    30,
    $storage
);

// 恢复未完成的事务
$recovered = $txn->recover();

echo "已恢复 " . count($recovered) . " 个事务\n";
foreach ($recovered as $txnId) {
    echo "  - {$txnId}\n";
}
```

### 清理过期事务

```php
// 清理 7 天前已完成的事务
$cleaned = $txn->cleanup(7);
echo "已清理 {$cleaned} 个过期事务\n";
```

## 高级用法

### 与 Fiber 结合

```php
use Kode\Fibers\Fibers;
use Kode\Fibers\Transaction\DistributedTransactionManager;

// 使用 Fiber 并行执行多个事务
$results = Fibers::parallel([
    'order_txn' => fn() => $orderTxn->execute(fn($tx) => processOrder()),
    'payment_txn' => fn() => $paymentTxn->execute(fn($tx) => processPayment()),
]);

// 使用超时保护
$result = Fibers::withTimeout(
    fn() => $txn->execute(fn($tx) => longRunningTask()),
    30.0
);
```

### 与 RPC 结合

```php
use Kode\Fibers\Rpc\RpcClient;
use Kode\Fibers\Transaction\DistributedTransactionManager;

$txn = new DistributedTransactionManager();

// 注册远程服务
$txn->register('remote_order',
    fn() => $rpcClient->call('order.prepare', []),
    fn() => $rpcClient->call('order.commit', []),
    fn() => $rpcClient->call('order.rollback', [])
);
```

### 监控指标

```php
// 获取事务统计
$stats = $txn->getStatus();
echo "事务ID: {$stats['transaction_id']}\n";
echo "模式: {$stats['mode']}\n";
echo "已提交: " . ($stats['committed'] ? '是' : '否') . "\n";
echo "已回滚: " . ($stats['rolled_back'] ? '是' : '否') . "\n";

echo "参与者状态:\n";
foreach ($stats['participants'] as $name => $status) {
    echo "  - {$name}: {$status}\n";
}
```

## 选择指南

| 场景 | 推荐模式 | 原因 |
|------|---------|------|
| 银行转账 | 2PC | 强一致性要求 |
| 订单创建 | TCC | 需要快速释放资源 |
| 复杂业务流程 | Saga | 松耦合，长流程 |
| 微服务集成 | TCC/Saga | 服务独立性强 |
| 单库多表 | 2PC | 数据库原生支持 |
