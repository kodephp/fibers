<?php

declare(strict_types=1);

namespace Kode\Fibers\Channel;

use Kode\Fibers\Exceptions\FiberException;
use Kode\Fibers\Context\Context;
use Fiber;
use Closure;
use RuntimeException;

/**
 * 纤程间通信通道
 */
class Channel
{
    /**
     * 通道名称
     *
     * @var string
     */
    protected string $name;

    /**
     * 缓冲区大小
     *
     * @var int
     */
    protected int $bufferSize;

    /**
     * 消息队列
     *
     * @var array
     */
    protected array $messages = [];

    /**
     * 等待发送的纤程队列
     *
     * @var array
     */
    protected array $senders = [];

    /**
     * 等待接收的纤程队列
     *
     * @var array
     */
    protected array $receivers = [];

    /**
     * 是否关闭
     *
     * @var bool
     */
    protected bool $closed = false;

    /**
     * 超时处理器
     *
     * @var array
     */
    protected array $timeoutHandlers = [];

    /**
     * 静态通道实例缓存
     *
     * @var array
     */
    protected static array $channels = [];

    /**
     * 构造函数
     *
     * @param string $name 通道名称
     * @param int $bufferSize 缓冲区大小
     */
    public function __construct(string $name = '', int $bufferSize = 0)
    {
        $this->name = $name;
        $this->bufferSize = $bufferSize;
    }

    /**
     * 创建或获取一个通道实例
     *
     * @param string $name 通道名称
     * @param int $bufferSize 缓冲区大小
     * @return self
     */
    public static function make(string $name, int $bufferSize = 0): self
    {
        if (!isset(self::$channels[$name])) {
            self::$channels[$name] = new self($name, $bufferSize);
        }

        return self::$channels[$name];
    }

    /**
     * 发送消息到通道
     *
     * @param mixed $message 消息内容
     * @param float|null $timeout 超时时间（秒）
     * @return bool 是否发送成功
     * @throws FiberException
     */
    public function push(mixed $message, ?float $timeout = null): bool
    {
        if ($this->closed) {
            throw new FiberException('Channel is closed');
        }

        // 如果有等待的接收者，直接发送
        if (!empty($this->receivers)) {
            $receiver = array_shift($this->receivers);
            $receiver($message);
            return true;
        }

        // 如果缓冲区未满，放入缓冲区
        if (count($this->messages) < $this->bufferSize) {
            $this->messages[] = $message;
            return true;
        }

        // 缓冲区已满，需要等待
        $fiber = Fiber::getCurrent();
        if (!$fiber) {
            throw new FiberException('Cannot push to channel outside of a fiber');
        }

        // 添加到发送者队列
        $waiter = function ($value) use ($fiber) {
            $fiber->resume($value);
        };

        $this->senders[] = $waiter;

        // 设置超时
        $timeoutId = null;
        if ($timeout !== null) {
            $timeoutId = $this->setTimeout($timeout, function () use ($fiber, $waiter, &$timeoutId) {
                // 从发送者队列中移除
                $this->removeSender($waiter);
                $this->clearTimeout($timeoutId);
                $fiber->throw(new RuntimeException('Push timeout'));
            });
        }

        // 挂起当前纤程
        $result = Fiber::suspend();

        // 清除超时
        if ($timeoutId) {
            $this->clearTimeout($timeoutId);
        }

        return $result;
    }

    /**
     * 从通道接收消息
     *
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 消息内容
     * @throws FiberException
     */
    public function pop(?float $timeout = null): mixed
    {
        if ($this->closed && empty($this->messages)) {
            return null;
        }

        // 如果缓冲区有消息，直接返回
        if (!empty($this->messages)) {
            $message = array_shift($this->messages);

            // 如果有等待的发送者，唤醒他们
            if (!empty($this->senders)) {
                $sender = array_shift($this->senders);
                $sender(true);
            }

            return $message;
        }

        // 如果通道已关闭，返回null
        if ($this->closed) {
            return null;
        }

        // 没有消息，需要等待
        $fiber = Fiber::getCurrent();
        if (!$fiber) {
            throw new FiberException('Cannot pop from channel outside of a fiber');
        }

        // 添加到接收者队列
        $waiter = function ($message) use ($fiber) {
            $fiber->resume($message);
        };

        $this->receivers[] = $waiter;

        // 设置超时
        $timeoutId = null;
        if ($timeout !== null) {
            $timeoutId = $this->setTimeout($timeout, function () use ($fiber, $waiter, &$timeoutId) {
                // 从接收者队列中移除
                $this->removeReceiver($waiter);
                $this->clearTimeout($timeoutId);
                $fiber->throw(new RuntimeException('Pop timeout'));
            });
        }

        // 挂起当前纤程
        $message = Fiber::suspend();

        // 清除超时
        if ($timeoutId) {
            $this->clearTimeout($timeoutId);
        }

        return $message;
    }

    /**
     * 关闭通道
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        // 唤醒所有等待的发送者和接收者
        foreach ($this->senders as $sender) {
            try {
                $sender(false);
            } catch (\Throwable) {
                // 忽略异常
            }
        }

        foreach ($this->receivers as $receiver) {
            try {
                $receiver(null);
            } catch (\Throwable) {
                // 忽略异常
            }
        }

        // 清空队列
        $this->senders = [];
        $this->receivers = [];

        // 从通道列表中移除
        if ($this->name && isset(self::$channels[$this->name])) {
            unset(self::$channels[$this->name]);
        }
    }

    /**
     * 获取通道名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 检查通道是否已关闭
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * 获取通道中消息的数量
     *
     * @return int
     */
    public function length(): int
    {
        return count($this->messages);
    }

    /**
     * 从发送者队列中移除一个发送者
     *
     * @param Closure $waiter 发送者等待函数
     * @return void
     */
    protected function removeSender(Closure $waiter): void
    {
        $index = array_search($waiter, $this->senders, true);
        if ($index !== false) {
            array_splice($this->senders, $index, 1);
        }
    }

    /**
     * 从接收者队列中移除一个接收者
     *
     * @param Closure $waiter 接收者等待函数
     * @return void
     */
    protected function removeReceiver(Closure $waiter): void
    {
        $index = array_search($waiter, $this->receivers, true);
        if ($index !== false) {
            array_splice($this->receivers, $index, 1);
        }
    }

    /**
     * 设置超时处理
     *
     * @param float $timeout 超时时间（秒）
     * @param Closure $handler 超时处理函数
     * @return int|string 超时ID
     */
    protected function setTimeout(float $timeout, Closure $handler): int|string
    {
        $timeoutId = uniqid('channel_timeout_', true);
        $this->timeoutHandlers[$timeoutId] = $handler;

        // 模拟超时处理
        // 实际使用时，可能需要集成事件循环来实现真正的异步超时
        $microseconds = (int)($timeout * 1000000);
        usleep($microseconds);
        
        // 检查通道是否已关闭或超时处理器是否已被清除
        if (!$this->closed && isset($this->timeoutHandlers[$timeoutId])) {
            $handler();
        }

        return $timeoutId;
    }

    /**
     * 清除超时处理
     *
     * @param int|string $timeoutId 超时ID
     * @return void
     */
    protected function clearTimeout(int|string $timeoutId): void
    {
        if (isset($this->timeoutHandlers[$timeoutId])) {
            unset($this->timeoutHandlers[$timeoutId]);
        }
    }

    /**
     * 获取所有活跃的通道
     *
     * @return array
     */
    public static function getActiveChannels(): array
    {
        return self::$channels;
    }

    /**
     * 创建一个MySQL操作通道
     *
     * @param string $name 通道名称
     * @param string $dsn 数据源名称
     * @param string $username 用户名
     * @param string $password 密码
     * @param array $options PDO选项
     * @return self
     */
    public static function mysql(string $name, string $dsn, string $username = '', string $password = '', array $options = []): self
    {
        $channel = self::make($name, 10);
        
        // 初始化MySQL连接
        $pdo = new \PDO($dsn, $username, $password, $options);
        
        // 设置连接上下文
        Context::set("mysql.{$name}", $pdo);
        
        // 返回通道
        return $channel;
    }

    /**
     * 创建一个Redis操作通道
     *
     * @param string $name 通道名称
     * @param string $host 主机名
     * @param int $port 端口号
     * @param string $password 密码
     * @param int $database 数据库索引
     * @return self
     */
    public static function redis(string $name, string $host = '127.0.0.1', int $port = 6379, string $password = '', int $database = 0): self
    {
        $channel = self::make($name, 10);
        
        // 初始化Redis连接
        $redis = new \Redis();
        $redis->connect($host, $port);
        
        if (!empty($password)) {
            $redis->auth($password);
        }
        
        if ($database > 0) {
            $redis->select($database);
        }
        
        // 设置连接上下文
        Context::set("redis.{$name}", $redis);
        
        // 返回通道
        return $channel;
    }

    /**
     * 创建一个HTTP请求通道
     *
     * @param string $name 通道名称
     * @param array $options 请求选项
     * @return self
     */
    public static function http(string $name, array $options = []): self
    {
        $channel = self::make($name, 10);
        
        // 设置HTTP请求上下文
        Context::set("http.{$name}", $options);
        
        // 返回通道
        return $channel;
    }
}
