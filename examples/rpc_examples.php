<?php

declare(strict_types=1);

namespace Kode\Fibers\Examples;

require __DIR__ . '/vendor/autoload.php';

use Kode\Fibers\Rpc\RpcClient;
use Kode\Fibers\Rpc\RpcServer;
use Kode\Fibers\Rpc\RpcException;
use Kode\Fibers\Rpc\GrpcClient;
use Kode\Fibers\Rpc\GrpcProtocol;
use Kode\Fibers\Rpc\WebSocketServer;
use Kode\Fibers\Rpc\WebSocketClient;

echo "=== RPC 通信示例 ===\n\n";

echo "1. JSON-RPC 客户端\n";
echo "--------------------\n";
$client = RpcClient::json('127.0.0.1', 8080);
$client->setHeaders(['X-App-Version' => '1.0']);
echo "JSON-RPC 客户端已创建\n";
echo "服务器地址: 127.0.0.1:8080\n\n";

echo "2. 批量调用示例\n";
echo "--------------------\n";
$batchCalls = [
    ['user.get', ['id' => 1]],
    ['user.get', ['id' => 2]],
    ['user.list', []],
];
echo "准备批量调用:\n";
foreach ($batchCalls as $i => $call) {
    echo "  [$i] {$call[0]}\n";
}
echo "批量调用需服务器运行才能执行\n\n";

echo "3. gRPC 客户端\n";
echo "--------------------\n";
$grpc = GrpcProtocol::createClient('127.0.0.1', 50051, 'user', 'UserService');
echo "gRPC 客户端已创建\n";
echo "服务器地址: 127.0.0.1:50051\n";
echo "包名: user, 服务名: UserService\n\n";

echo "4. WebSocket 客户端\n";
echo "--------------------\n";
try {
    $wsClient = new WebSocketClient('127.0.0.1', 8080);
    $wsClient->connect();
    echo "WebSocket 连接成功\n";
    
    $result = $wsClient->call('chat.send', ['text' => 'Hello!']);
    echo "发送消息: ";
    print_r($result);
    
    $wsClient->notify('chat.typing', ['user' => 'Demo']);
    echo "已发送通知\n";
    
    $wsClient->close();
    echo "连接已关闭\n";
} catch (\Throwable $e) {
    echo "WebSocket 连接失败 (服务器可能未运行): " . $e->getMessage() . "\n";
}
echo "\n";

echo "5. RPC 错误处理\n";
echo "--------------------\n";
try {
    $client->call('nonexistent.method', []);
} catch (RpcException $e) {
    echo "RPC 异常:\n";
    echo "  代码: " . $e->getErrorCode() . "\n";
    echo "  消息: " . $e->getMessage() . "\n";
}
echo "\n";

echo "6. 协议切换示例\n";
echo "--------------------\n";

$jsonClient = RpcClient::json('127.0.0.1', 8080);
echo "JSON 协议: " . get_class($jsonClient) . "\n";

$msgpackProtocol = new \Kode\Fibers\Rpc\MessagePackProtocol();
$msgpackClient = new RpcClient('127.0.0.1', 8080, '/rpc', $msgpackProtocol);
echo "MessagePack 协议: " . get_class($msgpackClient) . "\n\n";

echo "=== 示例完成 ===\n";
