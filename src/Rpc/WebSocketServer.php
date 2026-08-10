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
    protected array $allowedOrigins = [];

    public function __construct(string $host = '0.0.0.0', int $port = 8080)
    {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * 限制允许建立 WebSocket 连接的 Origin（跨站劫持防护）。
     *
     * 传入空数组表示不校验 Origin（默认行为，便于本地开发）；
     * 传入非空列表时，握手阶段 Origin 不在白名单内将被拒绝。
     *
     * @param string[] $origins 允许的 Origin 列表，如 ['https://app.example.com']
     * @return self
     */
    public function setAllowedOrigins(array $origins): self
    {
        $this->allowedOrigins = $origins;
        return $this;
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

        if (!isset($headers['Sec-WebSocket-Key'], $headers['Upgrade'], $headers['Connection'], $headers['Sec-WebSocket-Version'])) {
            fclose($client);
            return;
        }

        // 校验 WebSocket 握手必备字段
        if (strtolower($headers['Upgrade']) !== 'websocket'
            || !preg_match('/\bupgrade\b/i', $headers['Connection'])
            || $headers['Sec-WebSocket-Version'] !== '13') {
            fclose($client);
            return;
        }

        // 跨站 WebSocket 劫持（CSWSH）防护：配置了白名单时严格校验 Origin
        if ($this->allowedOrigins !== [] && isset($headers['Origin'])) {
            if (!in_array($headers['Origin'], $this->allowedOrigins, true)) {
                fclose($client);
                return;
            }
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
        stream_set_timeout($client, 1);

        $this->handleMessages($clientId);
    }

    /**
     * 读取 HTTP 头
     */
    protected function readHeaders($client): array
    {
        $headers = [];
        $maxLines = 64;
        while ($maxLines-- > 0 && ($line = fgets($client, 8192)) !== false) {
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
            $frame = $this->readFrame($client);

            if ($frame === null) {
                break;
            }

            $opcode = $frame['opcode'];

            // 控制帧：close / ping / pong 不进入业务处理，避免把控制帧当作 RPC 请求
            if ($opcode === 0x08) {
                break;
            }

            if ($opcode === 0x09) {
                // ping：回 pong，保持连接存活
                $this->sendFrame($client, $frame['payload'], 0x8A);
                continue;
            }

            if ($opcode === 0x0A) {
                // pong：忽略
                continue;
            }

            if ($opcode !== 0x01 && $opcode !== 0x02) {
                // 仅处理文本/二进制数据帧
                continue;
            }

            $data = $frame['payload'];

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
            return $this->createErrorResponse($id, -32603, 'Internal error');
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
     *
     * 客户端发往服务端的帧按 RFC 6455 必须带掩码，这里读取并还原真实载荷；
     * 同时处理 close/ping/pong 控制帧的语义，返回结构化结果交由调用方分流。
     *
     * @return array{opcode:int, payload:string, fin:bool}|null 连接关闭或读取出错时返回 null
     */
    protected function readFrame($client): ?array
    {
        $firstByte = fread($client, 1);
        if ($firstByte === false || $firstByte === '') {
            return null;
        }

        $b0 = ord($firstByte);
        $opcode = $b0 & 0x0f;
        $fin = ($b0 & 0x80) !== 0;

        $secondByte = fread($client, 1);
        if ($secondByte === false) {
            return null;
        }

        $b1 = ord($secondByte);
        $masked = ($b1 & 0x80) !== 0;
        $length = $b1 & 0x7f;

        if ($length === 126) {
            $ext = fread($client, 2);
            if ($ext === false) {
                return null;
            }
            $length = unpack('n', $ext)[1];
        } elseif ($length === 127) {
            $ext = fread($client, 8);
            if ($ext === false) {
                return null;
            }
            $length = unpack('J', $ext)[1];
        }

        $maskKey = '';
        if ($masked) {
            $maskKey = fread($client, 4);
            if ($maskKey === false) {
                return null;
            }
        }

        $payload = '';
        while (strlen($payload) < $length) {
            $chunk = fread($client, $length - strlen($payload));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $payload .= $chunk;
        }

        if ($masked && $maskKey !== '' && strlen($payload) === $length) {
            $payload = $this->unmask($payload, $maskKey);
        }

        return ['opcode' => $opcode, 'payload' => $payload, 'fin' => $fin];
    }

    /**
     * 还原被掩码的载荷
     */
    protected function unmask(string $payload, string $maskKey): string
    {
        $len = strlen($payload);
        $out = '';

        for ($i = 0; $i < $len; $i++) {
            $out .= $payload[$i] ^ $maskKey[$i % 4];
        }

        return $out;
    }

    /**
     * 发送 WebSocket 帧
     *
     * 服务端发往客户端的帧不得带掩码；$opcode 默认 0x81（FIN + 文本帧），
     * ping 响应使用 0x8A（FIN + pong）。
     */
    protected function sendFrame($client, string $data, int $opcode = 0x81): void
    {
        $length = strlen($data);

        $frame = chr($opcode);

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
