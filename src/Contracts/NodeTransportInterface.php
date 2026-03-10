<?php

declare(strict_types=1);

namespace Kode\Fibers\Contracts;

interface NodeTransportInterface
{
    public function send(string $nodeId, array $tasks): array;
}
