<?php

declare(strict_types=1);

namespace Kode\Fibers\Rpc;

/**
 * gRPC 协议支持
 *
 * 提供简单的 gRPC 通信能力
 */
class GrpcProtocol
{
    protected string $package;
    protected string $service;
    protected array $headers = [];

    public const COMPRESS_NONE = 0;
    public const COMPRESS_DEFLATE = 1;

    public function __construct(string $package = '', string $service = '')
    {
        $this->package = $package;
        $this->service = $service;
    }

    /**
     * 编码请求
     */
    public function encodeRequest(string $method, array $data): string
    {
        $message = $this->encodeMessage($data);
        
        $header = pack('c', 0);
        $header .= pack('N', strlen($message));
        
        return $header . $message;
    }

    /**
     * 解码响应
     */
    public function decodeResponse(string $data): array
    {
        if (strlen($data) < 5) {
            return ['status' => 'error', 'message' => 'Invalid response'];
        }

        $compression = unpack('c', $data[0])[1];
        $length = unpack('N', substr($data, 1, 4))[1];
        
        $messageData = substr($data, 5, $length);
        
        if ($compression === self::COMPRESS_DEFLATE) {
            $messageData = gzuncompress($messageData);
        }

        return [
            'status' => 'ok',
            'compression' => $compression,
            'data' => $this->decodeMessage($messageData),
        ];
    }

    /**
     * 编码消息体
     */
    protected function encodeMessage(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this->encodeVarint(strlen($json)) . $json;
    }

    /**
     * 解码消息体
     */
    protected function decodeMessage(string $data): array
    {
        return json_decode($data, true) ?? [];
    }

    /**
     * 编码 Varint
     */
    protected function encodeVarint(int $value): string
    {
        $result = '';
        while ($value > 0x7f) {
            $result .= chr(($value & 0x7f) | 0x80);
            $value >>= 7;
        }
        $result .= chr($value);
        return $result;
    }

    /**
     * 解码 Varint
     */
    protected function decodeVarint(string &$data): int
    {
        $result = 0;
        $shift = 0;
        
        while (isset($data[0])) {
            $byte = ord($data[0]);
            $data = substr($data, 1);
            $result |= ($byte & 0x7f) << $shift;
            
            if (($byte & 0x80) === 0) {
                break;
            }
            
            $shift += 7;
        }
        
        return $result;
    }

    /**
     * 创建 gRPC 客户端
     */
    public static function createClient(string $host, int $port, string $package, string $service): GrpcClient
    {
        return new GrpcClient($host, $port, $package, $service);
    }
}

/**
 * gRPC 客户端
 */
class GrpcClient
{
    protected string $host;
    protected int $port;
    protected GrpcProtocol $protocol;
    protected int $timeout;
    protected array $metadata = [];

    public function __construct(
        string $host,
        int $port,
        string $package = '',
        string $service = '',
        int $timeout = 30
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->protocol = new GrpcProtocol($package, $service);
        $this->timeout = $timeout;
    }

    /**
     * 设置元数据
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * 调用 gRPC 方法
     */
    public function call(string $method, array $data): array
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        
        if (!$socket) {
            throw new RpcException("Connection failed: {$errstr} ({$errno})", -1);
        }

        stream_set_timeout($socket, $this->timeout);

        $request = $this->protocol->encodeRequest($method, $data);
        
        $httpRequest = "POST /{$method} HTTP/1.1\r\n";
        $httpRequest .= "Host: {$this->host}:{$this->port}\r\n";
        $httpRequest .= "Content-Type: application/grpc\r\n";
        $httpRequest .= "TE: trailers\r\n";
        $httpRequest .= "Content-Length: " . strlen($request) . "\r\n";
        
        foreach ($this->metadata as $key => $value) {
            $httpRequest .= "{$key}: {$value}\r\n";
        }
        
        $httpRequest .= "\r\n";
        $httpRequest .= $request;

        fwrite($socket, $httpRequest);

        $response = '';
        while (!feof($socket)) {
            $response .= fgets($socket, 4096);
        }

        fclose($socket);

        return $this->parseResponse($response);
    }

    /**
     * 解析 HTTP/2 gRPC 响应
     */
    protected function parseResponse(string $response): array
    {
        $parts = explode("\r\n\r\n", $response, 2);
        
        if (count($parts) < 2) {
            return ['status' => 'error', 'message' => 'Invalid response format'];
        }

        $headers = $parts[0];
        $body = $parts[1];

        if (preg_match('/grpc-status:\s*(\d+)/i', $headers, $matches)) {
            $status = (int) $matches[1];
            if ($status !== 0) {
                preg_match('/grpc-message:\s*(.+)/i', $headers, $msgMatches);
                return [
                    'status' => 'error',
                    'code' => $status,
                    'message' => $msgMatches[1] ?? 'Unknown error',
                ];
            }
        }

        if (strpos($body, "\x00") === 0) {
            $length = unpack('N', substr($body, 1, 4))[1];
            $body = substr($body, 5, $length);
        }

        return [
            'status' => 'ok',
            'data' => json_decode($body, true) ?? [],
        ];
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
    }
}
