<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kode\Fibers\Fibers;

$tasks = [
    'sync-user' => ['priority' => 'high'],
    'sync-order' => ['priority' => 'high'],
    'sync-payment' => ['priority' => 'medium'],
    'sync-report' => ['priority' => 'low'],
];

$nodes = [
    'node-1' => ['healthy' => true, 'tags' => ['primary']],
    'node-2' => ['healthy' => true, 'tags' => ['secondary']],
];

$dispatch = Fibers::scheduleDistributed($tasks, $nodes);

echo "分配结果:\n";
print_r($dispatch['assignments']);
