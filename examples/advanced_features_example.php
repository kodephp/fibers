<?php

/**
 * Advanced Features Example
 * 
 * This example demonstrates the advanced features of the nova/fibers package:
 * - Context passing between fibers
 * - Distributed scheduler usage
 * - Fiber profiler
 * - ORM integration
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Context\Context;
use Nova\Fibers\Context\ContextManager;
use Nova\Fibers\Scheduler\LocalScheduler;
use Nova\Fibers\Profiler\FiberProfiler;
use Nova\Fibers\ORM\EloquentORMAdapter;
use Nova\Fibers\ORM\FixturesAdapter;

// Enable the fiber profiler
FiberProfiler::enable();

// Initialize ContextManager if not already initialized
if (!ContextManager::getCurrentContext()) {
    ContextManager::setCurrentContext(new Context('main_context'));
}

echo "=== Advanced Features Example ===\n\n";

// 1. Context passing example
echo "1. Context Passing Example:\n";
$context = ContextManager::getCurrentContext();
if ($context) {
    $context = $context->withValue('user_id', 123);
    $context = $context->withValue('request_id', uniqid('req_'));
    $context = $context->withValue('timestamp', microtime(true));
    ContextManager::setCurrentContext($context);
} else {
    $context = new Context('main_context');
    $context = $context->withValue('user_id', 123);
    $context = $context->withValue('request_id', uniqid('req_'));
    $context = $context->withValue('timestamp', microtime(true));
    ContextManager::setCurrentContext($context);
}

$fiber1 = new Fiber(function() {
    echo "  Fiber 1 started\n";
    FiberProfiler::startFiber('fiber1', 'Data Processing');
    
    $context = ContextManager::getCurrentContext();
    if ($context) {
        echo "  User ID: " . $context->value('user_id') . "\n";
        echo "  Request ID: " . $context->value('request_id') . "\n";
    }
    
    // Simulate some work
    usleep(100000); // 100ms
    
    // Modify context
    if ($context) {
        $context = $context->withValue('fiber1_result', 'Processed successfully');
        ContextManager::setCurrentContext($context);
    }
    
    FiberProfiler::endFiber('fiber1', 'completed');
    echo "  Fiber 1 completed\n";
});

$fiber1->start();

// Check context after fiber execution
$updatedContext = ContextManager::getCurrentContext();
if ($updatedContext) {
    echo "  Context after fiber execution:\n";
    echo "    fiber1_result: " . ($updatedContext->value('fiber1_result') ?? 'N/A') . "\n\n";
} else {
    echo "  Context after fiber execution: N/A\n\n";
}

// 2. Distributed scheduler example
echo "2. Distributed Scheduler Example:\n";
$scheduler = new LocalScheduler([
    'size' => 4,
    'max_exec_time' => 10
]);

$taskId = $scheduler->submit(
    function() {
        echo "    Task running in scheduler\n";
        usleep(200000); // 200ms
        return "Task completed at " . date('Y-m-d H:i:s');
    },
    (new Context('scheduler_context'))->withValue('job_type', 'example_job')
);

echo "  Submitted task with ID: {$taskId}\n";

try {
    $result = $scheduler->getResult($taskId, 5.0); // 5 second timeout
    echo "  Task result: {$result}\n";
} catch (RuntimeException $e) {
    echo "  Task failed: " . $e->getMessage() . "\n";
}

$status = $scheduler->getStatus($taskId);
echo "  Final task status: {$status}\n";

// 3. ORM integration example
echo "3. ORM Integration Example:\n";
try {
    // Initialize ORM adapter
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create test table
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)");
    
    $ormAdapter = new EloquentORMAdapter($pdo);
    $fixturesAdapter = new FixturesAdapter($ormAdapter);
    
    // Load fixtures
    $fixtures = [
        'users' => [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com']
        ]
    ];
    
    $context = new Context('orm_context');
    $context = $context->withValue('user_id', 1);
    $inserted = $fixturesAdapter->load($fixtures, $context);
    echo "  Loaded {$inserted} fixture records\n";
    
    // Query data
    $users = $ormAdapter->query("SELECT * FROM users WHERE name LIKE ?", ['%John%'], $context);
    echo "  Found " . count($users) . " users matching query\n";
    
    // Purge data
    $deleted = $fixturesAdapter->purge(['users'], $context);
    echo "  Purged {$deleted} records\n";
} catch (Exception $e) {
    echo "  ORM example failed: " . $e->getMessage() . "\n";
}

echo "\n=== Advanced Features Example Completed ===\n";