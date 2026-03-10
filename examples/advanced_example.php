<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Fibers\Fibers;
use Kode\Fibers\Support\CpuInfo;

echo "Kode/Fibers 健壮架构高级示例\n";
echo "============================\n\n";

$cpuCount = CpuInfo::get();
$recommendedPoolSize = min($cpuCount * 2, 16);

echo "系统信息:\n";
echo "  CPU 核心数: {$cpuCount}\n";
echo "  推荐并发度: {$recommendedPoolSize}\n\n";

echo "1) 容错单任务执行（resilientRun）\n";
$single = Fibers::resilientRun(
    static function () {
        if (random_int(1, 10) <= 7) {
            throw new RuntimeException('模拟瞬时失败');
        }
        return 'single-task-ok';
    },
    [
        'max_retries' => 2,
        'failure_threshold' => 3,
        'fallback' => static fn() => 'single-task-fallback',
    ]
);
echo "  结果: {$single}\n\n";

echo "2) 健壮批处理（resilientBatch）\n";
$batchInput = [
    'order-1001' => 10,
    'order-1002' => 20,
    'order-1003' => 30,
    'order-1004' => 40,
];

$batchOutput = Fibers::resilientBatch(
    $batchInput,
    static function (int $value, string $key, int $attempt): int {
        if ($key === 'order-1002' && $attempt === 0) {
            throw new RuntimeException('order-1002 首次失败');
        }
        return $value * 3;
    },
    [
        'concurrency' => min(4, $recommendedPoolSize),
        'max_retries' => 1,
        'fail_fast' => false,
        'failure_threshold' => 4,
    ]
);

echo "  成功数: " . $batchOutput['metrics']['success'] . "\n";
echo "  失败数: " . $batchOutput['metrics']['errors'] . "\n";
echo "  熔断状态: " . $batchOutput['breaker']['state'] . "\n\n";

echo "3) 分布式任务分配（scheduleDistributedAdvanced）\n";
$tasks = [
    'sync-user' => ['required_tags' => ['db']],
    'sync-order' => ['required_tags' => ['db', 'cache']],
    'sync-report' => ['required_tags' => ['analytics']],
];

$nodes = [
    'node-a' => ['healthy' => true, 'tags' => ['db', 'cache']],
    'node-b' => ['healthy' => true, 'tags' => ['db']],
    'node-c' => ['healthy' => true, 'tags' => ['analytics']],
];

$dispatch = Fibers::scheduleDistributedAdvanced(
    $tasks,
    $nodes,
    ['unhealthy_nodes' => ['node-c']]
);

echo "  已分配节点数: " . count($dispatch['assignments']) . "\n";
echo "  未分配任务数: " . count($dispatch['unassigned']) . "\n\n";

echo "4) 运行时能力检查\n";
$features = Fibers::runtimeFeatures();
echo "  PHP版本: {$features['php_version']}\n";
echo "  PHP8.5+: " . ($features['php85_or_newer'] ? 'yes' : 'no') . "\n";

echo "\n高级示例执行完成\n";
