# RPC 通信指南

## 概述

`kode/fibers` 提供完整的 RPC 通信解决方案，支持多种协议：

| 协议 | 客户端类 | 服务器类 | 特点 |
|------|---------|---------|------|
| JSON-RPC | `RpcClient` | `RpcServer` | 通用协议，跨语言 |
| MessagePack-RPC | `RpcClient` | `RpcServer` | 高效二进制格式 |
| gRPC | `GrpcClient` | - | 高性能，类型安全 |
| WebSocket | `WebSocketClient` | `WebSocketServer` | 双向通信，实时推送 |

## JSON-RPC

### 客户端

```php
use Kode\Fibers\Rpc\RpcClient;

// 创建客户端
$client = RpcClient::json('127.0.0.1', 8080);

// 调用远程方法
$result = $client->call('user.get', ['id' => 1]);
echo $result['name'];

// 批量调用
$results = $client->batchCall([
    ['user.get', ['id' => 1]],
    ['user.get', ['id' => 2]],
    ['user.list', []],
]);

// 设置请求头
$client->setHeaders(['Authorization' => 'Bearer token']);
```

### 服务器

```php
use Kode\Fibers\Rpc\RpcServer;

// 创建服务器
$server = new RpcServer('0.0.0.0', 8080);

// 注册服务
$server->register('user', function ($method, $params) {
    return match ($method) {
        'get' => [
            'id' => $params['id'] ?? 0,
            'name' => 'User ' . ($params['id'] ?? 0),
            'email' => 'user@example.com',
        ],
        'list' => [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ],
        'create' => [
            'id' => rand(100, 999),
            'name' => $params['name'] ?? '',
        ],
        default => throw new \Kode\Fibers\Rpc\RpcException('Unknown method', -32601),
    };
});

// 添加中间件
$server->middleware(function ($request) {
    // 验证请求
    if (!isset($request['method'])) {
        throw new \Exception('Invalid request');
    }
    return $request;
});

// 启动服务器
$server->start();
```

### 运行示例

```bash
# 终端 1: 启动服务器
php examples/rpc_server.php

# 终端 2: 运行客户端
php examples/rpc_client.php
```

## MessagePack-RPC

MessagePack 是一种高效的二进制序列化格式，比 JSON 更小更快。

```php
use Kode\Fibers\Rpc\RpcClient;
use Kode\Fibers\Rpc\Protocols\MessagePackProtocol;

// 使用 MessagePack 协议
$client = new RpcClient('127.0.0.1', 8080, '/rpc', new MessagePackProtocol());

$result = $client->call('user.get', ['id' => 1]);
```

## gRPC

### 客户端

```php
use Kode\Fibers\Rpc\GrpcProtocol;
use Kode\Fibers\Rpc\GrpcClient;

// 创建客户端
$client = GrpcProtocol::createClient('127.0.0.1', 50051, 'user', 'UserService');

// 设置元数据
$client->withMetadata([
    'authorization' => 'Bearer token',
    'x-request-id' => uniqid(),
]);

// 调用方法
$result = $client->call('GetUser', ['id' => 1]);
print_r($result);
```

### gRPC 特点

- **Varint 编码**：更紧凑的整数表示
- **压缩支持**：支持 deflate 压缩
- **流式响应**：支持服务器端流
- **元数据**：支持自定义元数据头

## WebSocket RPC

WebSocket 提供双向通信能力，适合实时应用。

### 服务器

```php
use Kode\Fibers\Rpc\WebSocketServer;

// 创建服务器
$server = new WebSocketServer('0.0.0.0', 8080);

// 注册服务
$server->register('chat', function ($method, $params) {
    return match ($method) {
        'send' => [
            'success' => true,
            'message_id' => uniqid(),
            'timestamp' => time(),
        ],
        'history' => [
            ['id' => 1, 'user' => 'Alice', 'text' => 'Hello!'],
            ['id' => 2, 'user' => 'Bob', 'text' => 'Hi!'],
        ],
        default => throw new \Kode\Fibers\Rpc\RpcException('Unknown method', -32601),
    };
});

// 广播消息
$server->broadcast([
    'type' => 'system',
    'message' => 'Server is running',
]);

// 启动服务器
$server->start();
```

### 客户端

```php
use Kode\Fibers\Rpc\WebSocketClient;

// 创建客户端
$client = new WebSocketClient('127.0.0.1', 8080);

// 连接服务器
$client->connect();

// 调用方法（同步）
$result = $client->call('chat.send', [
    'text' => 'Hello, World!',
    'channel' => 'general',
]);

// 发送通知（异步，不等待响应）
$client->notify('chat.typing', ['user' => 'Alice']);

// 关闭连接
$client->close();
```

## 错误处理

```php
use Kode\Fibers\Rpc\RpcClient;
use Kode\Fibers\Rpc\RpcException;

try {
    $result = $client->call('user.get', ['id' => 999]);
} catch (RpcException $e) {
    echo "错误代码: " . $e->getErrorCode() . "\n";
    echo "错误消息: " . $e->getMessage() . "\n";
    echo "错误数据: ";
    print_r($e->getErrorData());
}
```

## 最佳实践

### 1. 连接池

```php
class RpcPool
{
    protected array $clients = [];
    protected int $maxConnections;

    public function getClient(): RpcClient
    {
        if (empty($this->clients)) {
            return new RpcClient('127.0.0.1', 8080);
        }
        return array_pop($this->clients);
    }

    public function releaseClient(RpcClient $client): void
    {
        if (count($this->clients) < $this->maxConnections) {
            $this->clients[] = $client;
        }
    }
}
```

### 2. 超时处理

```php
use Kode\Fibers\Fibers;

$result = Fibers::withTimeout(
    fn() => $client->call('heavy.task', ['data' => 'x']),
    5.0 // 5秒超时
);
```

### 3. 熔断保护

```php
use Kode\Fibers\Core\CircuitBreaker;

$breaker = new CircuitBreaker(5, 30);

$result = $breaker->execute(
    fn() => $client->call('user.get', ['id' => 1]),
    fn() => ['id' => 0, 'name' => 'Fallback'], // 降级返回
    'user-service'
);
```

### 4. 重试机制

```php
use Kode\Fibers\Fibers;

$result = Fibers::resilientRun(
    fn() => $client->call('user.get', ['id' => 1]),
    [
        'max_retries' => 3,
        'failure_threshold' => 3,
        'fallback' => fn() => ['id' => 0, 'name' => 'Default'],
    ]
);
```
