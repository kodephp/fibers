<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Facades\Fiber;

/**
 * 数据库操作示例
 * 
 * 此脚本演示了如何使用 nova/fibers 包进行并发数据库操作
 */

echo "Nova Fibers Database Operations Example\n";
echo str_repeat("=", 50) . "\n";

// 模拟数据库连接配置
$dbConfig = [
    'host' => 'localhost',
    'port' => 3306,
    'dbname' => 'test',
    'username' => 'root',
    'password' => 'password'
];

echo "Simulating database operations with Fiber concurrency...\n";

// 模拟数据库查询函数
function simulateDatabaseQuery($query, $delay = 1) {
    echo "Executing query: {$query}\n";
    
    // 模拟数据库查询延迟
    usleep($delay * 1000000);
    
    // 模拟查询结果
    return [
        'query' => $query,
        'result' => [
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2'],
            ['id' => 3, 'name' => 'User 3']
        ],
        'execution_time' => $delay
    ];
}

// 定义数据库操作任务
$tasks = [
    fn() => simulateDatabaseQuery("SELECT * FROM users WHERE id = 1", 0.5),
    fn() => simulateDatabaseQuery("SELECT * FROM orders WHERE user_id = 1", 0.8),
    fn() => simulateDatabaseQuery("SELECT * FROM products LIMIT 10", 0.3),
    fn() => simulateDatabaseQuery("SELECT COUNT(*) as total FROM logs", 0.2),
    fn() => simulateDatabaseQuery("SELECT * FROM categories", 0.4)
];

// 使用纤程池并发执行数据库操作
$start = microtime(true);

try {
    $pool = new FiberPool(['size' => 5]);
    $results = $pool->concurrent($tasks);
    
    $elapsed = microtime(true) - $start;
    
    echo "\nAll database operations completed in " . number_format($elapsed, 2) . " seconds\n";
    echo "Results:\n";
    
    foreach ($results as $i => $result) {
        $query = $result['query'];
        $count = count($result['result']);
        $time = $result['execution_time'];
        echo "  " . ($i + 1) . ". {$query} - {$count} rows in {$time}s\n";
    }
} catch (Exception $e) {
    echo "Database operation failed: " . $e->getMessage() . "\n";
}

// 演示事务处理
echo "\nSimulating database transactions...\n";

function simulateTransaction($transactionId) {
    echo "Starting transaction #{$transactionId}\n";
    
    // 模拟事务处理步骤
    usleep(300000); // 0.3秒
    echo "  Step 1 completed for transaction #{$transactionId}\n";
    
    usleep(200000); // 0.2秒
    echo "  Step 2 completed for transaction #{$transactionId}\n";
    
    usleep(100000); // 0.1秒
    echo "  Step 3 completed for transaction #{$transactionId}\n";
    
    echo "Transaction #{$transactionId} committed successfully\n";
    return "Transaction #{$transactionId} completed";
}

// 并发执行多个事务
$transactionTasks = [];
for ($i = 1; $i <= 5; $i++) {
    $transactionTasks[] = fn() => simulateTransaction($i);
}

$start = microtime(true);

try {
    $pool = new FiberPool(['size' => 3]);
    $results = $pool->concurrent($transactionTasks);
    
    $elapsed = microtime(true) - $start;
    
    echo "\nAll transactions completed in " . number_format($elapsed, 2) . " seconds\n";
    echo "Transaction results:\n";
    
    foreach ($results as $result) {
        echo "  - {$result}\n";
    }
} catch (Exception $e) {
    echo "Transaction failed: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Database Operations Example Completed!\n";