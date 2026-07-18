<?php

declare(strict_types=1);

namespace Kode\Fibers\Core;

use Kode\Fibers\Contracts\NodeTransportInterface;

/**
 * 内存型节点传输
 *
 * 用于本地测试与单机分发场景，将任务以回执数组形式返回。
 */
class InMemoryNodeTransport implements NodeTransportInterface
{
    #[\Override]
    public function send(string $nodeId, array $tasks): array
    {
        $receipts = [];
        foreach ($tasks as $taskId => $payload) {
            $receipts[$taskId] = [
                'node' => $nodeId,
                'accepted' => true,
                'payload' => $payload,
                'timestamp' => microtime(true),
            ];
        }

        return $receipts;
    }
}
