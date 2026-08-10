<?php

declare(strict_types=1);

namespace Kode\Fibers\Rpc;

use Kode\Fibers\Fibers;

/**
 * RPC 服务器
 *
 * 提供基于 Fiber 的高并发 RPC 服务
 */
class RpcServer
{
    protected string $host;
    protected int $port;
    protected RpcProtocolInterface $protocol;
    protected array $services = [];
    protected array $middleware = [];
    protected bool $running = false;
    protected int $maxBatchSize = 100;

    public function __construct(
        string $host = '0.0.0.0',
        int $port = 8080,
        ?RpcProtocolInterface $protocol = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->protocol = $protocol ?? new JsonRpcProtocol();
    }

    /**
     * 注册服务
     */
    public function register(string $name, callable $handler): self
    {
        $this->services[$name] = $handler;
        return $this;
    }

    /**
     * 添加中间件
     */
    public function middleware(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * 启动服务器
     */
    public function start(): void
    {
        $this->running = true;
        $socket = @stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);
        
        if (!$socket) {
            throw new \RuntimeException("Failed to create server: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, 1);

        echo "RPC Server started on {$this->host}:{$this->port}\n";

        while ($this->running) {
            $client = @stream_socket_accept($socket, 1);
            
            if ($client === false) {
                continue;
            }

            Fibers::go(function () use ($client) {
                $this->handleClient($client);
            });
        }

        fclose($socket);
    }

    /**
     * 停止服务器
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * 处理客户端连接
     */
    protected function handleClient($client): void
    {
        stream_set_timeout($client, 5);

        try {
            $http = $this->readHttpRequest($client);

            if ($http === null) {
                return;
            }

            $request = $this->protocol->decode($http['body']);

            // 协议层约定返回数组；若实现异常返回非数组（如畸形输入被吞成 null），
            // 统一按「解析错误」响应，避免把非数组传入 handleRequest(array) 触发 TypeError。
            if (!is_array($request)) {
                $body = $this->protocol->encode(
                    $this->createErrorResponse(null, -32700, 'Parse error')
                );
                $httpResponse = "HTTP/1.1 400 Bad Request\r\n";
                $httpResponse .= "Content-Type: application/json\r\n";
                $httpResponse .= "Content-Length: " . strlen($body) . "\r\n";
                $httpResponse .= "Connection: close\r\n\r\n";
                $httpResponse .= $body;
                fwrite($client, $httpResponse);

                return;
            }

            $isBatch = isset($request[0]);

            if ($isBatch) {
                if (count($request) > $this->maxBatchSize) {
                    $body = $this->protocol->encode(
                        $this->createErrorResponse(null, -32600, "批量请求过大（上限 {$this->maxBatchSize}）")
                    );
                    $httpResponse = "HTTP/1.1 413 Payload Too Large\r\n";
                    $httpResponse .= "Content-Type: application/json\r\n";
                    $httpResponse .= "Content-Length: " . strlen($body) . "\r\n";
                    $httpResponse .= "Connection: close\r\n\r\n";
                    $httpResponse .= $body;
                    fwrite($client, $httpResponse);
                    return;
                }
                $response = $this->handleBatch($request);
            } else {
                $response = $this->handleRequest($request);
            }

            $body = $this->protocol->encode($response);
            
            $httpResponse = "HTTP/1.1 200 OK\r\n";
            $httpResponse .= "Content-Type: application/json\r\n";
            $httpResponse .= "Content-Length: " . strlen($body) . "\r\n";
            $httpResponse .= "Connection: close\r\n";
            $httpResponse .= "\r\n";
            $httpResponse .= $body;

            fwrite($client, $httpResponse);
        } catch (\Throwable $e) {
            $error = [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                ],
                'id' => null,
            ];
            fwrite($client, $this->protocol->encode($error));
        } finally {
            fclose($client);
        }
    }

    /**
     * 从连接读取一个完整的 HTTP 请求，返回头与 body
     *
     * 客户端（RpcClient）发送的是标准 HTTP/1.1 POST，不能直接把含请求头的
     * 原始数据交给协议层解码——那样会把 HTTP 头误当成 JSON 导致解析失败。
     * 这里按 Content-Length 精确读取请求体，再对 body 做协议解码。
     *
     * @return array{headers:array, body:string}|null 读取失败或连接关闭时返回 null
     */
    protected function readHttpRequest($client): ?array
    {
        $buffer = '';

        // 先读全请求头（以空行结尾）
        while (!str_contains($buffer, "\r\n\r\n")) {
            $chunk = @fread($client, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;
        }

        if ($buffer === '' || !str_contains($buffer, "\r\n\r\n")) {
            return null;
        }

        [$headerPart, $body] = explode("\r\n\r\n", $buffer, 2);
        $headers = $this->parseRawHeaders($headerPart);
        $length = (int) ($headers['CONTENT-LENGTH'] ?? 0);

        // 按 Content-Length 补足 body（HTTP 请求体可能分片到达）
        while (strlen($body) < $length) {
            $chunk = @fread($client, $length - strlen($body));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        return ['headers' => $headers, 'body' => substr($body, 0, $length)];
    }

    /**
     * 将原始 HTTP 头文本解析为大写键数组
     */
    protected function parseRawHeaders(string $raw): array
    {
        $headers = [];

        foreach (explode("\r\n", $raw) as $index => $line) {
            if ($index === 0) {
                continue; // 请求行（POST /rpc HTTP/1.1）
            }
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $m)) {
                $headers[strtoupper(trim($m[1]))] = trim($m[2]);
            }
        }

        return $headers;
    }

    /**
     * 处理请求
     */
    protected function handleRequest(array $request): array
    {
        foreach ($this->middleware as $middleware) {
            $request = $middleware($request) ?? $request;
        }

        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];
        $id = $request['id'] ?? null;

        if (!str_contains($method, '.')) {
            return $this->createErrorResponse($id, -32601, 'Method not found');
        }

        [$service, $methodName] = explode('.', $method, 2);

        if (!isset($this->services[$service])) {
            return $this->createErrorResponse($id, -32601, "Service '{$service}' not found");
        }

        try {
            $result = ($this->services[$service])($methodName, $params);
            
            return [
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $id,
            ];
        } catch (\Throwable $e) {
            return $this->createErrorResponse($id, -32603, 'Internal error');
        }
    }

    /**
     * 处理批量请求
     */
    protected function handleBatch(array $requests): array
    {
        $responses = [];
        
        foreach ($requests as $request) {
            $responses[] = $this->handleRequest($request);
        }

        return $responses;
    }

    /**
     * 创建错误响应
     */
    protected function createErrorResponse(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'id' => $id,
        ];
    }

    /**
     * 获取服务列表
     */
    public function getServices(): array
    {
        return array_keys($this->services);
    }

    /**
     * 检查是否运行中
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
}
