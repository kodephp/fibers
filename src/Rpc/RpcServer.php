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
        try {
            $data = fread($client, 65536);
            
            if ($data === false || $data === '') {
                fclose($client);
                return;
            }

            $request = $this->protocol->decode($data);

            $isBatch = is_array($request) && isset($request[0]);
            
            if ($isBatch) {
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
                    'message' => 'Internal error: ' . $e->getMessage(),
                ],
                'id' => null,
            ];
            fwrite($client, $this->protocol->encode($error));
        } finally {
            fclose($client);
        }
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
            return $this->createErrorResponse($id, -32603, $e->getMessage());
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
