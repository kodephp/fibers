<?php

declare(strict_types=1);

namespace Kode\Fibers\Rpc;

/**
 * WebSocket RPC 服务器
 *
 * 提供基于 WebSocket 的双向 RPC 通信
 */
class WebSocketServer
{
    protected string $host;
    protected int $port;
    protected $socket;
    protected array $clients = [];
    protected array $services = [];
    protected array $middleware = [];
    protected bool $running = false;

    public function __construct(string $host = '0.0.0.0', int $port = 8080)
    {
        $this->host = $host;
        $this->port = $port;
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
        
        $address = "tcp://{$this->host}:{$this->port}";
        $this->socket = stream_socket_server($address, $errno, $errstr);
        
        if (!$this->socket) {
            throw new \RuntimeException("Failed to create server: {$errstr} ({$errno})");
        }

        stream_set_timeout($this->socket, 1);

        echo "WebSocket Server started on {$this->host}:{$this->port}\n";

        while ($this->running) {
            $client = @stream_socket_accept($this->socket, 1);
            
            if ($client === false) {
                usleep(1000);
                continue;
            }

            $this->handleConnection($client);
        }

        fclose($this->socket);
    }

    /**
     * 停止服务器
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * 处理连接
     */
    protected function handleConnection($client): void
    {
        $headers = $this->readHeaders($client);
        
        if (!isset($headers['Sec-WebSocket-Key'])) {
            fclose($client);
            return;
        }

        $key = $headers['Sec-WebSocket-Key'];
        $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        
        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: {$acceptKey}\r\n";
        $response .= "\r\n";

        fwrite($client, $response);

        $clientId = uniqid('ws_');
        $this->clients[$clientId] = $client;

        $this->handleMessages($clientId);
    }

    /**
     * 读取 HTTP 头
     */
    protected function readHeaders($client): array
    {
        $headers = [];
        while (($line = fgets($client)) !== false) {
            $line = trim($line);
            if ($line === '') {
                break;
            }
            if (preg_match('/^(\S+):\s*(.+)$/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }
        return $headers;
    }

    /**
     * 处理消息
     */
    protected function handleMessages(string $clientId): void
    {
        $client = $this->clients[$clientId];
        
        while ($this->running) {
            $data = $this->readFrame($client);
            
            if ($data === null || $data === false) {
                break;
            }

            if ($data === '') {
                continue;
            }

            $message = json_decode($data, true);
            
            if ($message === null) {
                continue;
            }

            $response = $this->handleRequest($message);
            
            $this->sendFrame($client, json_encode($response));
        }

        $this->disconnect($clientId);
    }

    /**
     * 处理请求
     */
    protected function handleRequest(array $request): array
    {
        foreach ($this->middleware as $middleware) {
            $request = $middleware($request) ?? $request;
        }

        $id = $request['id'] ?? null;
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];

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
     * 读取 WebSocket 帧
     */
    protected function readFrame($client): ?string
    {
        $firstByte = fread($client, 1);
        if ($firstByte === false || $firstByte === '') {
            return null;
        }

        $opcode = ord($firstByte) & 0x0f;
        
        if ($opcode === 0x08) {
            return null;
        }

        $secondByte = fread($client, 1);
        if ($secondByte === false) {
            return null;
        }

        $length = ord($secondByte) & 0x7f;
        
        if ($length === 126) {
            $ext = fread($client, 2);
            $length = unpack('n', $ext)[1];
        } elseif ($length === 127) {
            $ext = fread($client, 8);
            $length = unpack('J', $ext)[1];
        }

        $payload = '';
        while (strlen($payload) < $length) {
            $chunk = fread($client, $length - strlen($payload));
            if ($chunk === false) {
                break;
            }
            $payload .= $chunk;
        }

        return $payload;
    }

    /**
     * 发送 WebSocket 帧
     */
    protected function sendFrame($client, string $data): void
    {
        $length = strlen($data);
        
        $frame = chr(0x81);
        
        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }
        
        $frame .= $data;
        
        fwrite($client, $frame);
    }

    /**
     * 断开连接
     */
    protected function disconnect(string $clientId): void
    {
        if (isset($this->clients[$clientId])) {
            fclose($this->clients[$clientId]);
            unset($this->clients[$clientId]);
        }
    }

    /**
     * 广播消息
     */
    public function broadcast(array $message): void
    {
        $data = json_encode($message);
        
        foreach ($this->clients as $client) {
            $this->sendFrame($client, $data);
        }
    }

    /**
     * 获取连接数
     */
    public function getConnectionCount(): int
    {
        return count($this->clients);
    }

    /**
     * 检查是否运行中
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
}

/**
 * WebSocket RPC 客户端
 */
class WebSocketClient
{
    protected string $host;
    protected int $port;
    protected $socket;
    protected ?string $clientId = null;
    protected bool $connected = false;
    protected int $timeout;
    protected array $pendingCalls = [];

    public function __construct(string $host, int $port, int $timeout = 30)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
    }

    /**
     * 连接服务器
     */
    public function connect(): void
    {
        $address = "tcp://{$this->host}:{$this->port}";
        $this->socket = @stream_socket_client($address, $errno, $errstr, $this->timeout);
        
        if (!$this->socket) {
            throw new RpcException("Connection failed: {$errstr} ({$errno})", -1);
        }

        stream_set_timeout($this->socket, $this->timeout);

        $key = base64_encode(random_bytes(16));
        
        $request = "GET / HTTP/1.1\r\n";
        $request .= "Host: {$this->host}:{$this->port}\r\n";
        $request .= "Upgrade: websocket\r\n";
        $request .= "Connection: Upgrade\r\n";
        $request .= "Sec-WebSocket-Key: {$key}\r\n";
        $request .= "Sec-WebSocket-Version: 13\r\n";
        $request .= "\r\n";

        fwrite($this->socket, $request);

        $response = fgets($this->socket);
        if (!str_contains($response, '101')) {
            throw new RpcException('WebSocket handshake failed', -1);
        }

        while (($line = fgets($this->socket)) !== false) {
            if (trim($line) === '') {
                break;
            }
        }

        $this->connected = true;
    }

    /**
     * 调用远程方法
     */
    public function call(string $method, array $params = []): mixed
    {
        if (!$this->connected) {
            $this->connect();
        }

        $id = uniqid('call_');
        
        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $id,
        ];

        $this->sendFrame(json_encode($request));

        $response = $this->receiveFrame();
        
        if ($response === null) {
            throw new RpcException('No response received', -1);
        }

        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            throw new RpcException(
                $data['error']['message'] ?? 'Unknown error',
                $data['error']['code'] ?? -32603
            );
        }

        return $data['result'] ?? null;
    }

    /**
     * 发送通知（不等待响应）
     */
    public function notify(string $method, array $params = []): void
    {
        if (!$this->connected) {
            $this->connect();
        }

        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ];

        $this->sendFrame(json_encode($request));
    }

    /**
     * 接收帧
     */
    protected function receiveFrame(): ?string
    {
        $firstByte = fread($this->socket, 1);
        if ($firstByte === false || $firstByte === '') {
            return null;
        }

        $opcode = ord($firstByte) & 0x0f;
        
        if ($opcode === 0x08) {
            $this->connected = false;
            return null;
        }

        $secondByte = fread($this->socket, 1);
        if ($secondByte === false) {
            return null;
        }

        $length = ord($secondByte) & 0x7f;
        
        if ($length === 126) {
            $ext = fread($this->socket, 2);
            $length = unpack('n', $ext)[1];
        } elseif ($length === 127) {
            $ext = fread($this->socket, 8);
            $length = unpack('J', $ext)[1];
        }

        $payload = '';
        while (strlen($payload) < $length) {
            $chunk = fread($this->socket, $length - strlen($payload));
            if ($chunk === false) {
                break;
            }
            $payload .= $chunk;
        }

        return $payload;
    }

    /**
     * 发送帧
     */
    protected function sendFrame(string $data): void
    {
        $length = strlen($data);
        
        $frame = chr(0x81);
        
        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }
        
        $frame .= $data;
        
        fwrite($this->socket, $frame);
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        if ($this->connected && $this->socket) {
            $this->sendFrame('');
            fclose($this->socket);
        }
        $this->connected = false;
    }

    /**
     * 检查连接状态
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * 析构
     */
    public function __destruct()
    {
        $this->close();
    }
}
