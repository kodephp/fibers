<?php

/**
 * Web服务器示例
 * 
 * 展示如何在Web应用中使用nova/fibers包
 */

// 引入Composer自动加载器
require_once __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Channel\Channel;
use Nova\Fibers\Support\Environment;
use Nova\Fibers\Support\CpuInfo;

// 检查环境是否支持纤程
if (!Environment::checkFiberSupport()) {
    die('当前环境不支持纤程，需要PHP 8.1或更高版本');
}

// 创建一个简单的HTTP服务器
class SimpleHttpServer {
    private $host;
    private $port;
    private $pool;
    
    public function __construct($host = '127.0.0.1', $port = 8080) {
        $this->host = $host;
        $this->port = $port;
        $this->pool = new FiberPool([
            'size' => CpuInfo::get() * 2,
            'name' => 'web-server-pool'
        ]);
    }
    
    public function start($testMode = false) {
        echo "Starting HTTP server on {$this->host}:{$this->port}\n";
        if (!$testMode) {
            echo "Press Ctrl+C to stop the server\n\n";
        }
        
        // 创建一个TCP服务器
        $socket = stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);
        if (!$socket) {
            die("Failed to create server: $errstr ($errno)\n");
        }
        
        // 在测试模式下，只处理一个请求
        if ($testMode) {
            $client = stream_socket_accept($socket, 5); // 5秒超时
            if ($client) {
                $this->handleRequest($client);
            }
            fclose($socket);
            return;
        }
        
        // 创建一个Channel用于处理请求
        $requestChannel = Channel::make('requests', 10);
        
        // 启动请求处理纤程
        \Nova\Fibers\Facades\Fiber::run(function() use ($requestChannel) {
            while (true) {
                $client = $requestChannel->pop();
                if ($client === null) {
                    break;
                }
                
                // 在纤程池中处理请求
                $this->pool->concurrent([function() use ($client) {
                    $this->handleRequest($client);
                }]);
            }
        });
        
        // 主循环：接受客户端连接
        while (true) {
            $client = stream_socket_accept($socket, -1);
            if ($client) {
                // 将客户端连接发送到Channel
                $requestChannel->push($client);
            }
        }
        
        fclose($socket);
    }
    
    private function handleRequest($client) {
        // 读取请求
        $request = fread($client, 1024);
        
        // 解析请求行
        $lines = explode("\n", $request);
        $requestLine = explode(" ", $lines[0]);
        $method = $requestLine[0];
        $path = $requestLine[1];
        
        echo "Received {$method} request for {$path}\n";
        
        // 根据路径生成响应
        $response = $this->generateResponse($path);
        
        // 发送响应
        fwrite($client, $response);
        fclose($client);
    }
    
    private function generateResponse($path) {
        $statusCode = 200;
        $content = "";
        
        switch ($path) {
            case '/':
                $content = "<h1>Welcome to Nova Fibers HTTP Server</h1>";
                $content .= "<p>Server is running with PHP " . PHP_VERSION . "</p>";
                $content .= "<p>CPU cores: " . CpuInfo::get() . "</p>";
                break;
                
            case '/fibers':
                // 模拟一些并发任务
                $tasks = [];
                for ($i = 1; $i <= 5; $i++) {
                    $tasks[] = function() use ($i) {
                        usleep(100000); // 100ms
                        return "Task $i completed";
                    };
                }
                
                $results = $this->pool->concurrent($tasks);
                $content = "<h1>Concurrent Tasks Results</h1><ul>";
                foreach ($results as $result) {
                    $content .= "<li>$result</li>";
                }
                $content .= "</ul>";
                break;
                
            case '/timeout':
                try {
                    // 模拟一个会超时的任务
                    $results = $this->pool->concurrent([
                        function() {
                            usleep(200000); // 200ms
                            return 'Slow task';
                        }
                    ], 0.1); // 100ms timeout
                    
                    $content = "<h1>Timeout Test</h1><p>Result: " . json_encode($results) . "</p>";
                } catch (RuntimeException $e) {
                    $content = "<h1>Timeout Test</h1><p>Error: " . $e->getMessage() . "</p>";
                }
                break;
                
            default:
                $statusCode = 404;
                $content = "<h1>404 Not Found</h1><p>The requested URL was not found on this server.</p>";
                break;
        }
        
        $statusText = $statusCode === 200 ? 'OK' : 'Not Found';
        $response = "HTTP/1.1 {$statusCode} {$statusText}\r\n";
        $response .= "Content-Type: text/html; charset=UTF-8\r\n";
        $response .= "Connection: close\r\n";
        $response .= "\r\n";
        $response .= "<html><body>{$content}</body></html>";
        
        return $response;
    }
}

// 检查是否以测试模式运行
$testMode = isset($argv[1]) && $argv[1] === 'test';

// 启动服务器
$server = new SimpleHttpServer('127.0.0.1', 8080);
$server->start($testMode);