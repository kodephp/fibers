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

/**
 * JSON RPC 协议
 */
class JsonRpcProtocol implements RpcProtocolInterface
{
    public function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function decode(string $data): array
    {
        return json_decode($data, true) ?? [];
    }

    public function getName(): string
    {
        return 'json';
    }
}

/**
 * MessagePack RPC 协议
 */
class MessagePackProtocol implements RpcProtocolInterface
{
    public function encode(array $data): string
    {
        if (function_exists('msgpack_pack')) {
            return msgpack_pack($data);
        }
        return (string) json_encode($data);
    }

    public function decode(string $data): array
    {
        if (function_exists('msgpack_unpack')) {
            $result = msgpack_unpack($data);
            return is_array($result) ? $result : [];
        }
        return json_decode($data, true) ?? [];
    }

    public function getName(): string
    {
        return 'msgpack';
    }
}

/**
 * Protocol Buffers RPC 协议（简单实现）
 */
class ProtobufProtocol implements RpcProtocolInterface
{
    public function encode(array $data): string
    {
        return json_encode($data);
    }

    public function decode(string $data): array
    {
        return json_decode($data, true) ?? [];
    }

    public function getName(): string
    {
        return 'protobuf';
    }
}
