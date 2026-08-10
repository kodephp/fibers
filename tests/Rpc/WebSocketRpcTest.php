<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests\Rpc;

use Kode\Fibers\Rpc\WebSocketClient;
use Kode\Fibers\Rpc\WebSocketServer;
use PHPUnit\Framework\TestCase;

/**
 * WebSocket RPC 往返回归测试。
 *
 * 重点防范历史缺陷：服务端曾在一个消息循环里读取两帧（把请求帧丢弃后
 * 又阻塞等待第二帧），导致客户端永远收不到响应。本测试验证：
 *  - 单次调用能正确返回结果；
 *  - 同一连接上的连续多次调用（echo / math）均正常；
 *  - 连接可正常关闭。
 */
class WebSocketRpcTest extends TestCase
{
    private int $port;
    private int $serverPid = 0;

    protected function setUp(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl 扩展不可用，无法在子进程内启动 WebSocket 服务器');
        }

        $this->port = 18100 + (getmypid() % 100);
        $this->serverPid = $this->startServer($this->port);
        // 等待握手端口就绪
        usleep(300_000);
    }

    protected function tearDown(): void
    {
        if ($this->serverPid > 0) {
            posix_kill($this->serverPid, SIGTERM);
            pcntl_waitpid($this->serverPid, $status);
        }
    }

    private function startServer(int $port): int
    {
        $pid = pcntl_fork();

        if ($pid === 0) {
            // 子进程：吞掉服务器自身的 echo 输出，避免污染测试输出
            ob_start();

            $server = new WebSocketServer('127.0.0.1', $port);
            $server->register('echo', function (string $method, array $params) {
                return $params['msg'] ?? null;
            });
            $server->register('math', function (string $method, array $params) {
                if ($method === 'add') {
                    return ($params[0] ?? 0) + ($params[1] ?? 0);
                }
                return null;
            });

            $server->start();
            ob_end_clean();
            exit(0);
        }

        return $pid;
    }

    public function testSingleCallReturnsResult(): void
    {
        $client = new WebSocketClient('127.0.0.1', $this->port);
        try {
            $result = $client->call('echo.ping', ['msg' => 'hello-world']);
            $this->assertSame('hello-world', $result);
        } finally {
            $client->close();
        }
    }

    public function testConsecutiveCallsOnSameConnection(): void
    {
        $client = new WebSocketClient('127.0.0.1', $this->port);
        try {
            $this->assertSame('hello-world', $client->call('echo.ping', ['msg' => 'hello-world']));
            $this->assertSame(5, $client->call('math.add', [2, 3]));
            $this->assertSame(0, $client->call('math.add', [0, 0]));
        } finally {
            $client->close();
        }
    }

    public function testResultIsDecodedNotRaw(): void
    {
        $client = new WebSocketClient('127.0.0.1', $this->port);
        try {
            $result = $client->call('math.add', [10, 32]);
            $this->assertSame(42, $result);
            $this->assertIsInt($result);
        } finally {
            $client->close();
        }
    }
}
