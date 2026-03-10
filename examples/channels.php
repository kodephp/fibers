<?php

/**
 * Kode/Fibers - 通道通信示例
 * 
 * 这个示例展示了如何使用Kode/Fibers包中的Channel类进行纤程间通信，包括：
 * - 创建带缓冲区的通道
 * - 发送和接收消息
 * - 超时控制
 * - 生产者-消费者模式
 * - 数据库和HTTP通道的使用
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Fibers\Fibers;
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Context\Context;

// 示例1: 基本的通道通信
function basicChannelCommunication() {
    echo "\n--- 示例1: 基本的通道通信 ---", PHP_EOL;
    
    // 创建一个命名通道，缓冲区大小为2
    $channel = Channel::make('basic-channel', 2);
    
    // 发送消息到通道（在主线程中）
    echo "主线程: 发送消息1", PHP_EOL;
    $channel->push("消息1");
    
    echo "主线程: 发送消息2", PHP_EOL;
    $channel->push("消息2");
    
    // 创建一个纤程来接收消息
    Fibers::run(function() use ($channel) {
        echo "纤程: 尝试接收消息", PHP_EOL;
        $message1 = $channel->pop();
        echo "纤程: 收到消息1: ", $message1, PHP_EOL;
        
        $message2 = $channel->pop();
        echo "纤程: 收到消息2: ", $message2, PHP_EOL;
    });
    
    // 关闭通道
    $channel->close();
}

// 示例2: 生产者-消费者模式
function producerConsumerPattern() {
    echo "\n--- 示例2: 生产者-消费者模式 ---", PHP_EOL;
    
    // 创建一个命名通道，缓冲区大小为3
    $channel = Channel::make('product-channel', 3);
    
    // 启动生产者纤程
    Fibers::run(function() use ($channel) {
        for ($i = 1; $i <= 5; $i++) {
            $product = "产品{$i}";
            echo "生产者: 生产{$product}", PHP_EOL;
            $channel->push($product);
            Fibers::sleep(0.3); // 模拟生产过程
        }
        echo "生产者: 完成生产，关闭通道", PHP_EOL;
        $channel->close();
    });
    
    // 启动消费者纤程
    Fibers::run(function() use ($channel) {
        while (true) {
            try {
                $product = $channel->pop(1); // 1秒超时
                if ($product === null) {
                    echo "消费者: 通道已关闭，退出消费", PHP_EOL;
                    break;
                }
                echo "消费者: 消费{$product}", PHP_EOL;
                Fibers::sleep(0.5); // 模拟消费过程
            } catch (RuntimeException $e) {
                echo "消费者: 等待超时", PHP_EOL;
            }
        }
    });
    
    // 等待所有纤程完成
    Fibers::sleep(5);
}

// 示例3: 带超时的消息收发
function channelWithTimeout() {
    echo "\n--- 示例3: 带超时的消息收发 ---", PHP_EOL;
    
    $channel = Channel::make('timeout-channel');
    
    // 尝试在超时前接收消息（会超时）
    try {
        echo "尝试接收消息，500毫秒后超时...", PHP_EOL;
        $message = $channel->pop(0.5); // 0.5秒超时
        echo "收到消息: ", $message, PHP_EOL; // 不会执行到这里
    } catch (RuntimeException $e) {
        echo "接收超时: ", $e->getMessage(), PHP_EOL;
    }
    
    // 尝试在超时前发送消息（会超时）
    try {
        echo "尝试发送消息，500毫秒后超时...", PHP_EOL;
        $result = Fibers::run(function() use ($channel) {
            return $channel->push("超时测试消息", 0.5); // 0.5秒超时
        });
        echo "发送结果: ", $result ? "成功" : "失败", PHP_EOL; // 不会执行到这里
    } catch (RuntimeException $e) {
        echo "发送超时: ", $e->getMessage(), PHP_EOL;
    }
    
    // 关闭通道
    $channel->close();
}

// 示例4: MySQL通道（实际使用时需要配置正确的数据库连接）
function mysqlChannelExample() {
    echo "\n--- 示例4: MySQL通道示例（需要配置数据库） ---", PHP_EOL;
    
    try {
        // 配置MySQL连接（需要根据实际环境修改）
        $dsn = 'mysql:host=localhost;dbname=test;charset=utf8mb4';
        $username = 'root';
        $password = '';
        
        // 创建MySQL通道
        $mysqlChannel = Channel::mysql('mysql-db', $dsn, $username, $password);
        echo "MySQL通道创建成功", PHP_EOL;
        
        // 在纤程中执行数据库操作
        Fibers::run(function() use ($mysqlChannel) {
            try {
                // 从上下文中获取PDO实例
                $pdo = Context::get('mysql.mysql-db');
                
                // 执行查询
                $stmt = $pdo->query('SELECT VERSION() as version');
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo "MySQL版本: ", $result['version'], PHP_EOL;
                
                // 发送结果到通道
                $mysqlChannel->push($result);
            } catch (Exception $e) {
                echo "数据库操作失败: ", $e->getMessage(), PHP_EOL;
            }
        });
        
        // 等待并获取结果
        Fibers::sleep(1);
        if ($mysqlChannel->length() > 0) {
            $result = $mysqlChannel->pop();
            echo "从MySQL通道收到结果: ", json_encode($result), PHP_EOL;
        }
        
    } catch (Exception $e) {
        echo "MySQL通道创建失败: ", $e->getMessage(), PHP_EOL;
        echo "提示: 请确保已配置正确的数据库连接参数", PHP_EOL;
    }
}

// 示例5: HTTP通道
function httpChannelExample() {
    echo "\n--- 示例5: HTTP通道示例 ---", PHP_EOL;
    
    // 创建HTTP通道
    $httpChannel = Channel::http('http-client', [
        'timeout' => 5,
        'headers' => ['User-Agent' => 'Kode/Fibers']
    ]);
    
    // 在纤程中执行HTTP请求
    Fibers::run(function() use ($httpChannel) {
        try {
            $url = 'https://httpbin.org/get';
            echo "执行HTTP请求: ", $url, PHP_EOL;
            
            // 使用PHP的file_get_contents函数
            $response = file_get_contents($url, false, stream_context_create([
                'http' => [
                    'header' => "User-Agent: Kode/Fibers\r\n"
                ]
            ]));
            
            // 解析响应
            $data = json_decode($response, true);
            
            // 发送结果到通道
            $httpChannel->push($data);
            
        } catch (Exception $e) {
            echo "HTTP请求失败: ", $e->getMessage(), PHP_EOL;
        }
    });
    
    // 等待并获取结果
    Fibers::sleep(3);
    if ($httpChannel->length() > 0) {
        $result = $httpChannel->pop();
        echo "从HTTP通道收到结果: ", substr(json_encode($result), 0, 100), "...", PHP_EOL;
    }
}

// 运行所有示例
echo "====== Kode/Fibers 通道通信示例 ======", PHP_EOL;
basicChannelCommunication();
Fibers::sleep(1); // 等待示例1完成

producerConsumerPattern();
Fibers::sleep(1); // 等待示例2完成

channelWithTimeout();
Fibers::sleep(1); // 等待示例3完成

mysqlChannelExample();
Fibers::sleep(1); // 等待示例4完成

httpChannelExample();
Fibers::sleep(1); // 等待示例5完成

echo "\n====== 示例执行完毕 ======", PHP_EOL;