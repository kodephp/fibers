<?php

declare(strict_types=1);

namespace Kode\Fibers\Rpc;

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
        // JSON 解码失败时返回的分隔符必须是数组，调用方（RpcServer / RpcClient）
        // 对结果按数组处理；非数组 JSON（标量/null）一律降级为空数组并经上层
        // 统一返回「解析错误」，避免 TypeError 崩溃。
        $decoded = json_decode($data, true, 512, JSON_BIGINT_AS_STRING);

        return is_array($decoded) ? $decoded : [];
    }

    public function getName(): string
    {
        return 'json';
    }
}
