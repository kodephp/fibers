<?php

declare(strict_types=1);

namespace Kode\Fibers\Rpc;

/**
 * MessagePack RPC 协议
 */
class MessagePackProtocol implements RpcProtocolInterface
{
    #[\Override]
    public function encode(array $data): string
    {
        if (function_exists('msgpack_pack')) {
            return msgpack_pack($data);
        }

        return (string) json_encode($data);
    }

    #[\Override]
    public function decode(string $data): array
    {
        if (function_exists('msgpack_unpack')) {
            $result = msgpack_unpack($data);
            return is_array($result) ? $result : [];
        }

        $decoded = json_decode($data, true, 512, JSON_BIGINT_AS_STRING);

        return is_array($decoded) ? $decoded : [];
    }

    #[\Override]
    public function getName(): string
    {
        return 'msgpack';
    }
}
