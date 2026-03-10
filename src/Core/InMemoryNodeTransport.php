<?php

declare(strict_types=1);

namespace Kode\Fibers\Core;

use Kode\Fibers\Contracts\NodeTransportInterface;

class InMemoryNodeTransport implements NodeTransportInterface
{
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
