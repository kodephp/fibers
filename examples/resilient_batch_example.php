<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kode\Fibers\Fibers;

$items = [
    'task-a' => 10,
    'task-b' => 20,
    'task-c' => 30,
];

$result = Fibers::resilientBatch(
    $items,
    static function (int $value, string $key, int $attempt): int {
        if ($key === 'task-b' && $attempt === 0) {
            throw new RuntimeException('task-b 首次失败');
        }

        return $value * 2;
    },
    [
        'concurrency' => 2,
        'max_retries' => 1,
        'fail_fast' => false,
        'failure_threshold' => 3,
    ]
);

echo "成功结果:\n";
print_r($result['results']);

echo "失败任务:\n";
print_r(array_map(static fn(Throwable $e) => $e->getMessage(), $result['errors']));

echo "熔断状态:\n";
print_r($result['breaker']);
