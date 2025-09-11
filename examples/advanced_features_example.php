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

echo "=== Advanced Features Example ===\n\n";

// 1. Context passing example
echo "1. Context Passing Example:\n";
$context = new Context('main_context');
$context = $context->withValue('user_id', 123);
$context = $context->withValue('request_id', uniqid('req_'));
$context = $context->withValue('timestamp', microtime(true));

ContextManager::setCurrentContext($context);

$fiber1 = new Fiber(function() {
    echo "  Fiber 1 started\n";
    FiberProfiler::startFiber('fiber1', 'Data Processing');
    
    $context = ContextManager::getCurrentContext();
    echo "  User ID: " . $context->value('user_id') . "\n";
    echo "  Request ID: " . $context->value('request_id') . "\n";
    
    // Simulate some work
    usleep(100000); // 100ms
    
    // Modify context
    $context = $context->withValue('fiber1_result', 'Processed successfully');
    ContextManager::setCurrentContext($context);
    
    FiberProfiler::endFiber('fiber1', 'completed');
    echo "  Fiber 1 completed\n";
});

$fiber1->start();

// Check context after fiber execution
$updatedContext = ContextManager::getCurrentContext();
echo "  Context after fiber execution:\n";
echo "    fiber1_result: " . $updatedContext->value('fiber1_result') . "\n\n";

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

$clusterInfo = $scheduler->getClusterInfo();
echo "  Cluster info: " . json_encode($clusterInfo, JSON_PRETTY_PRINT) . "\n\n";

// 3. Profiler report
echo "3. Profiler Report:\n";
$report = FiberProfiler::getReport();
echo json_encode($report, JSON_PRETTY_PRINT) . "\n\n";

// 4. ORM example (simulated)
echo "4. ORM Integration Example:\n";
// Note: This is a simulated example as we don't have a real database connection
// In a real application, you would configure the database connection properly

class SimulatedORMAdapter implements \Nova\Fibers\ORM\ORMAdapterInterface
{
    public function query(string $query, array $params = [], ?Context $context = null): array
    {
        echo "    Executing query: {$query}\n";
        echo "    With params: " . json_encode($params) . "\n";
        
        // Simulate database result
        return [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com']
        ];
    }
    
    public function execute(string $query, array $params = [], ?Context $context = null): int
    {
        echo "    Executing update: {$query}\n";
        echo "    With params: " . json_encode($params) . "\n";
        
        // Simulate affected rows
        return 1;
    }
    
    public function beginTransaction(?Context $context = null): void
    {
        echo "    Beginning transaction\n";
    }
    
    public function commit(?Context $context = null): void
    {
        echo "    Committing transaction\n";
    }
    
    public function rollback(?Context $context = null): void
    {
        echo "    Rolling back transaction\n";
    }
    
    public function getConnectionStatus(): array
    {
        return ['status' => 'connected', 'driver' => 'simulated'];
    }
}

$ormAdapter = new SimulatedORMAdapter();
$fixtures = new FixturesAdapter($ormAdapter);

// Simulate loading fixtures
$fixturesData = [
    'users' => [
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com']
    ]
];

echo "  Loading fixtures...\n";
try {
    $inserted = $fixtures->load($fixturesData, (new Context('fixtures_context'))->withValue('operation', 'load_fixtures'));
    echo "  Inserted {$inserted} records\n";
} catch (Exception $e) {
    echo "  Failed to load fixtures: " . $e->getMessage() . "\n";
}

$status = $fixtures->getStatus((new Context('status_context'))->withValue('operation', 'check_status'));
echo "  ORM status: " . json_encode($status, JSON_PRETTY_PRINT) . "\n\n";

echo "=== Advanced Features Example Completed ===\n";

// Show final profiler stats
echo "\n=== Final Profiler Stats ===\n";
$stats = FiberProfiler::getStats();
foreach ($stats as $id => $stat) {
    echo "Fiber {$id} ({$stat['name']}): {$stat['status']} - " . 
         ($stat['duration'] ? number_format($stat['duration'] * 1000, 2) . 'ms' : 'N/A') . "\n";
}