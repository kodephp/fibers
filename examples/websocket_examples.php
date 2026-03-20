<?php

declare(strict_types=1);

namespace Kode\Fibers\Examples;

require __DIR__ . '/vendor/autoload.php';

use Kode\Fibers\Rpc\WebSocketServer;
use Kode\Fibers\Rpc\WebSocketClient;

echo "=== WebSocket 通信示例 ===\n\n";

echo "1. WebSocket 服务器\n";
echo "--------------------\n";
$server = new WebSocketServer('0.0.0.0', 8080);

// 注册聊天服务
$server->register('chat', function ($method, $params) {
    return match ($method) {
        'send' => [
            'success' => true,
            'message_id' => uniqid('msg_'),
            'timestamp' => time(),
            'from' => $params['user'] ?? 'anonymous',
            'text' => $params['text'] ?? '',
        ],
        'history' => [
            ['id' => 1, 'user' => 'Alice', 'text' => 'Hello!', 'timestamp' => time() - 3600],
            ['id' => 2, 'user' => 'Bob', 'text' => 'Hi there!', 'timestamp' => time() - 1800],
            ['id' => 3, 'user' => 'Charlie', 'text' => 'How are you?', 'timestamp' => time() - 600],
        ],
        'users' => [
            ['id' => 1, 'name' => 'Alice', 'status' => 'online'],
            ['id' => 2, 'name' => 'Bob', 'status' => 'online'],
            ['id' => 3, 'name' => 'Charlie', 'status' => 'away'],
        ],
        default => throw new \Kode\Fibers\Rpc\RpcException('Unknown method', -32601),
    };
});

// 注册通知服务
$server->register('notification', function ($method, $params) {
    return match ($method) {
        'send' => [
            'success' => true,
            'notification_id' => uniqid('notif_'),
        ],
        'list' => [
            ['id' => 1, 'type' => 'info', 'message' => 'System update scheduled'],
            ['id' => 2, 'type' => 'warning', 'message' => 'Maintenance in 5 minutes'],
        ],
        default => throw new \Kode\Fibers\Rpc\RpcException('Unknown method', -32601),
    };
});

echo "WebSocket 服务器配置:\n";
echo "  - 监听地址: 0.0.0.0:8080\n";
echo "  - 服务: chat, notification\n";
echo "  - 方法: send, history, users, list\n\n";

echo "启动服务器:\n";
echo "  \$server->start();\n";
echo "(实际运行需要 \$server->start() 启动)\n\n";

echo "2. WebSocket 客户端\n";
echo "--------------------\n";
echo "连接服务器:\n";
echo "  \$client = new WebSocketClient('127.0.0.1', 8080);\n";
echo "  \$client->connect();\n\n";

echo "发送消息:\n";
echo "  \$result = \$client->call('chat.send', [\n";
echo "      'user' => 'Demo',\n";
echo "      'text' => 'Hello, World!'\n";
echo "  ]);\n";
echo "  // 返回: ['success' => true, 'message_id' => '...', 'timestamp' => ...]\n\n";

echo "获取历史:\n";
echo "  \$history = \$client->call('chat.history', []);\n";
echo "  // 返回聊天历史记录\n\n";

echo "获取在线用户:\n";
echo "  \$users = \$client->call('chat.users', []);\n";
echo "  // 返回在线用户列表\n\n";

echo "发送通知:\n";
echo "  \$client->notify('notification.send', [\n";
echo "      'type' => 'info',\n";
echo "      'message' => 'New message received'\n";
echo "  ]);\n";
echo "  // notify 是异步的，不等待响应\n\n";

echo "3. 实际连接示例\n";
echo "--------------------\n";
try {
    $client = new WebSocketClient('127.0.0.1', 8080);
    $client->connect();
    echo "连接成功!\n";
    
    $result = $client->call('chat.send', [
        'user' => 'DemoUser',
        'text' => 'Hello from demo!'
    ]);
    echo "发送结果: ";
    print_r($result);
    
    $history = $client->call('chat.history', []);
    echo "\n历史消息数量: " . count($history) . "\n";
    
    $client->close();
    echo "连接已关闭\n";
} catch (\Throwable $e) {
    echo "WebSocket 连接失败 (服务器可能未运行): " . $e->getMessage() . "\n";
    echo "提示: 先运行 php examples/websocket_server.php 启动服务器\n";
}
echo "\n";

echo "4. 广播功能\n";
echo "--------------------\n";
echo "服务器广播消息:\n";
echo "  \$server->broadcast([\n";
echo "      'type' => 'system',\n";
echo "      'message' => 'Server is running'\n";
echo "  ]);\n\n";

echo "5. 中间件示例\n";
echo "--------------------\n";
$server = new WebSocketServer('0.0.0.0', 8080);

// 添加认证中间件
$server->middleware(function ($request) {
    $token = $request['headers']['Authorization'] ?? '';
    
    if (str_starts_with($token, 'Bearer ')) {
        $request['user'] = 'authenticated_user';
    } else {
        $request['user'] = 'anonymous';
    }
    
    return $request;
});

// 添加日志中间件
$server->middleware(function ($request) {
    $log = [
        'method' => $request['method'] ?? 'unknown',
        'timestamp' => time(),
        'user' => $request['user'] ?? 'anonymous',
    ];
    error_log(json_encode($log));
    return $request;
});

echo "已添加中间件:\n";
echo "  - 认证中间件: 验证 Bearer Token\n";
echo "  - 日志中间件: 记录请求日志\n\n";

echo "=== 示例完成 ===\n";
