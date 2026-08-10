<?php

declare(strict_types=1);

namespace Kode\Fibers\Rpc;

/**
 * RPC 协议接口
 */
interface RpcProtocolInterface
{
    public function encode(array $data): string;

    public function decode(string $data): array;

    public function getName(): string;
}
