<?php

declare(strict_types=1);

namespace Kode\Fibers\Rpc;

use Kode\Fibers\HttpClient\HttpClient;

/**
 * RPC 客户端
 *
 * 支持多种协议：JSON、MessagePack、Protocol Buffers
 */
class RpcClient
{
    protected string $host;
    protected int $port;
    protected string $path;
    protected RpcProtocolInterface $protocol;
    protected int $timeout;
    protected array $headers = [];

    public function __construct(
        string $host,
        int $port = 8080,
        string $path = '/rpc',
        ?RpcProtocolInterface $protocol = null,
        int $timeout = 30
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
        $this->protocol = $protocol ?? new JsonRpcProtocol();
        $this->timeout = $timeout;
    }

    /**
     * 设置协议
     */
    public function setProtocol(RpcProtocolInterface $protocol): self
    {
        $this->protocol = $protocol;
        return $this;
    }

    /**
     * 设置请求头
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * 调用远程方法
     */
    public function call(string $method, array $params = []): mixed
    {
        $id = $this->generateId();
        
        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $id,
        ];

        $body = $this->protocol->encode($request);
        
        $url = "http://{$this->host}:{$this->port}{$this->path}";
        
        $httpClient = new HttpClient();
        $response = $httpClient->post($url, $body, array_merge($this->headers, [
            'Content-Type' => 'application/json',
            'X-Rpc-Protocol' => $this->protocol->getName(),
        ]));

        $result = $this->protocol->decode($response->getBody());

        if (isset($result['error'])) {
            throw new RpcException(
                $result['error']['message'] ?? 'Unknown error',
                $result['error']['code'] ?? -32603
            );
        }

        return $result['result'] ?? null;
    }

    /**
     * 批量调用
     */
    public function batchCall(array $calls): array
    {
        $requests = [];
        $id = $this->generateId();

        foreach ($calls as $key => $call) {
            $requests[] = [
                'jsonrpc' => '2.0',
                'method' => $call['method'] ?? $call[0],
                'params' => $call['params'] ?? $call[1] ?? [],
                'id' => $id . '_' . $key,
            ];
        }

        $body = $this->protocol->encode($requests);
        $url = "http://{$this->host}:{$this->port}{$this->path}";

        $httpClient = new HttpClient();
        $response = $httpClient->post($url, $body, array_merge($this->headers, [
            'Content-Type' => 'application/json',
            'X-Rpc-Protocol' => $this->protocol->getName(),
        ]));

        $results = $this->protocol->decode($response->getBody());
        
        $output = [];
        foreach ($results as $index => $result) {
            if (isset($result['error'])) {
                $output[$index] = new RpcException(
                    $result['error']['message'] ?? 'Unknown error',
                    $result['error']['code'] ?? -32603
                );
            } else {
                $output[$index] = $result['result'] ?? null;
            }
        }

        return $output;
    }

    /**
     * 生成请求 ID
     */
    protected function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * 创建 JSON RPC 客户端
     */
    public static function json(string $host, int $port = 8080, string $path = '/rpc'): self
    {
        return new self($host, $port, $path, new JsonRpcProtocol());
    }

    /**
     * 创建 MessagePack RPC 客户端
     */
    public static function msgpack(string $host, int $port = 8080, string $path = '/rpc'): self
    {
        return new self($host, $port, $path, new MessagePackProtocol());
    }
}

/**
 * RPC 异常
 */
class RpcException extends \Exception
{
    protected int $errorCode;
    protected array $errorData = [];

    public function __construct(string $message, int $code = -32603, array $data = [])
    {
        parent::__construct($message, $code);
        $this->errorCode = $code;
        $this->errorData = $data;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getErrorData(): array
    {
        return $this->errorData;
    }
}
