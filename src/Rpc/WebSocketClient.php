<?php

declare(strict_types=1);

namespace Kode\Fibers\Rpc;

/**
 * WebSocket RPC 客户端
 *
 * 依据 RFC 6455，客户端发往服务端的帧必须带掩码（MASK 位置 1），
 * 否则合规的 WebSocket 服务端会拒绝连接。本实现统一对发送帧做掩码处理。
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
     *
     * 服务端发往客户端的帧不带掩码，这里直接按长度读取即可；
     * 遇到 close 帧返回 null，上层据此关闭连接；
     * 遇到服务端 ping（0x09）则回 pong 并继续读取下一帧，避免误把控制帧当业务响应。
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

        if ($opcode === 0x09) {
            // 服务端主动 ping：回 pong 后继续读取真正的业务帧
            $payload = $this->readPayload();
            $this->sendFrame($payload, 0x8A);
            return $this->receiveFrame();
        }

        $payload = $this->readPayload();

        return $payload;
    }

    /**
     * 读取帧长度字段与载荷主体
     *
     * 假定调用方已读取首字节（opcode/FIN），此处从长度字节开始读取，
     * 直到取满 payload 长度；读取失败（连接断开）时返回 null。
     */
    protected function readPayload(): ?string
    {
        $secondByte = fread($this->socket, 1);
        if ($secondByte === false) {
            return null;
        }

        $length = ord($secondByte) & 0x7f;

        if ($length === 126) {
            $ext = fread($this->socket, 2);
            if ($ext === false) {
                return null;
            }
            $length = unpack('n', $ext)[1];
        } elseif ($length === 127) {
            $ext = fread($this->socket, 8);
            if ($ext === false) {
                return null;
            }
            $length = unpack('J', $ext)[1];
        }

        $payload = '';
        while (strlen($payload) < $length) {
            $chunk = fread($this->socket, $length - strlen($payload));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $payload .= $chunk;
        }

        return $payload;
    }

    /**
     * 发送帧（客户端必须带掩码）
     */
    protected function sendFrame(string $data, int $opcode = 0x81): void
    {
        $length = strlen($data);
        $maskKey = random_bytes(4);

        // FIN + 指定 opcode；掩码位（0x80）必须置于「第二字节（长度字节）」，
        // 而非首字节（首字节的 0x80 是 FIN）。RFC 6455 规定客户端帧必带掩码。
        $frame = chr($opcode);

        if ($length <= 125) {
            $frame .= chr($length | 0x80);
        } elseif ($length <= 65535) {
            $frame .= chr(126 | 0x80) . pack('n', $length);
        } else {
            $frame .= chr(127 | 0x80) . pack('J', $length);
        }

        $frame .= $maskKey;

        for ($i = 0; $i < $length; $i++) {
            $frame .= $data[$i] ^ $maskKey[$i % 4];
        }

        fwrite($this->socket, $frame);
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        if ($this->connected && $this->socket) {
            // 发送标准 close 帧（0x88），通知对端正常关闭
            $this->sendFrame('', 0x88);
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
