<?php

declare(strict_types=1);

namespace Kode\Fibers\Channel;

use Kode\Context\Context;
use Kode\Fibers\Concurrency\Scheduler;
use Kode\Fibers\Concurrency\Suspension;
use Kode\Fibers\Concurrency\TimeoutException;
use Kode\Fibers\Exceptions\FiberException;

/**
 * 纤程间通信通道
 *
 * 支持无缓冲（同步交接）与有缓冲两种模式，所有等待均通过
 * {@see Scheduler} 挂起协程实现，不会阻塞进程。
 *
 * ```php
 * $ch = new Channel('jobs', 4);
 *
 * $scheduler->go(fn() => $ch->push('payload'));
 * $scheduler->go(fn() => $ch->pop(1.0));       // 最多等待 1 秒
 * ```
 */
class Channel
{
    /**
     * 通道名称
     */
    protected string $name;

    /**
     * 缓冲区大小（0 表示无缓冲，push 必须与 pop 同步交接）
     */
    protected int $bufferSize;

    /**
     * 缓冲区消息队列
     *
     * @var list<mixed>
     */
    protected array $messages = [];

    /**
     * 等待发送的协程
     *
     * @var array<int, array{suspension: Suspension, message: mixed}>
     */
    protected array $senders = [];

    /**
     * 等待接收的协程
     *
     * @var array<int, array{suspension: Suspension, select: array-key|null}>
     */
    protected array $receivers = [];

    /**
     * 等待者自增序号，用于 O(1) 定位与移除
     */
    protected int $waiterSequence = 0;

    /**
     * 是否已关闭
     */
    protected bool $closed = false;

    /**
     * 静态通道实例缓存
     *
     * @var array<string, self>
     */
    protected static array $channels = [];

    /**
     * @param string $name       通道名称
     * @param int    $bufferSize 缓冲区大小
     */
    public function __construct(string $name = '', int $bufferSize = 0)
    {
        if ($bufferSize < 0) {
            throw new FiberException('通道缓冲区大小不能为负数');
        }

        $this->name = $name;
        $this->bufferSize = $bufferSize;
    }

    /**
     * 创建或获取一个具名通道实例
     */
    public static function make(string $name, int $bufferSize = 0): self
    {
        return self::$channels[$name] ??= new self($name, $bufferSize);
    }

    /**
     * 发送消息到通道
     *
     * 缓冲区已满（或无缓冲且无接收者）时挂起当前协程等待。
     *
     * @param  mixed      $message 消息内容
     * @param  float|null $timeout 超时秒数，null 表示不限
     * @return bool 发送是否成功（通道在等待期间被关闭时返回 false）
     * @throws FiberException   通道已关闭 / 不在协程内
     * @throws TimeoutException 等待超时
     */
    public function push(mixed $message, ?float $timeout = null): bool
    {
        if ($this->closed) {
            throw new FiberException('Channel is closed');
        }

        if ($this->deliverToReceiver($message)) {
            return true;
        }

        if (count($this->messages) < $this->bufferSize) {
            $this->messages[] = $message;

            return true;
        }

        $scheduler = $this->requireScheduler('push');
        $suspension = $scheduler->park();
        $id = ++$this->waiterSequence;
        $this->senders[$id] = ['suspension' => $suspension, 'message' => $message];

        $timer = null;

        if ($timeout !== null) {
            $timer = $scheduler->delay($timeout, function () use ($id, $suspension, $timeout): void {
                unset($this->senders[$id]);
                $suspension->throw(new TimeoutException(
                    sprintf('向通道 %s 发送消息超时（%.3fs）', $this->name ?: '(anonymous)', $timeout)
                ));
            });
        }

        try {
            return (bool) $suspension->suspend();
        } finally {
            $timer?->cancel();
            unset($this->senders[$id]);
        }
    }

    /**
     * 非阻塞发送：无法立即完成时返回 false，不会挂起
     */
    public function tryPush(mixed $message): bool
    {
        if ($this->closed) {
            return false;
        }

        if ($this->deliverToReceiver($message)) {
            return true;
        }

        if (count($this->messages) < $this->bufferSize) {
            $this->messages[] = $message;

            return true;
        }

        return false;
    }

    /**
     * 从通道接收消息
     *
     * @param  float|null $timeout 超时秒数，null 表示不限
     * @return mixed 消息内容；通道已关闭且无消息时返回 null
     * @throws FiberException   不在协程内
     * @throws TimeoutException 等待超时
     */
    public function pop(?float $timeout = null): mixed
    {
        if ($this->tryPop($message)) {
            return $message;
        }

        if ($this->closed) {
            return null;
        }

        $scheduler = $this->requireScheduler('pop');
        $suspension = $scheduler->park();
        $id = ++$this->waiterSequence;
        $this->receivers[$id] = ['suspension' => $suspension, 'select' => null];

        $timer = null;

        if ($timeout !== null) {
            $timer = $scheduler->delay($timeout, function () use ($id, $suspension, $timeout): void {
                unset($this->receivers[$id]);
                $suspension->throw(new TimeoutException(
                    sprintf('从通道 %s 接收消息超时（%.3fs）', $this->name ?: '(anonymous)', $timeout)
                ));
            });
        }

        try {
            return $suspension->suspend();
        } finally {
            $timer?->cancel();
            unset($this->receivers[$id]);
        }
    }

    /**
     * 非阻塞接收
     *
     * 通过返回值区分「取到 null」与「无消息可取」，弥补 pop() 无法区分的问题。
     *
     * @param mixed $message 取到的消息（引用出参）
     */
    public function tryPop(mixed &$message = null): bool
    {
        if ($this->messages !== []) {
            $message = array_shift($this->messages);
            $this->promoteWaitingSender();

            return true;
        }

        // 无缓冲通道：直接从等待中的发送者手中接过消息
        while ($this->senders !== []) {
            $key = array_key_first($this->senders);
            $sender = $this->senders[$key];
            unset($this->senders[$key]);

            if (!$sender['suspension']->isPending()) {
                continue;
            }

            $message = $sender['message'];
            $sender['suspension']->resume(true);

            return true;
        }

        $message = null;

        return false;
    }

    /**
     * 多路复用接收：等待一组通道中第一个可读的消息
     *
     * @param  array<array-key, self> $channels 通道集合
     * @param  float|null             $timeout  超时秒数，null 表示不限
     * @return array{0: array-key, 1: mixed}|null 命中的 [通道键, 消息]；超时返回 null
     * @throws FiberException 不在协程内
     */
    public static function select(array $channels, ?float $timeout = null): ?array
    {
        if ($channels === []) {
            return null;
        }

        // 快速路径：已有可立即读取的通道
        foreach ($channels as $key => $channel) {
            if ($channel->tryPop($message)) {
                return [$key, $message];
            }
        }

        $openChannels = array_filter($channels, static fn(self $channel): bool => !$channel->isClosed());

        if ($openChannels === []) {
            return null;
        }

        $scheduler = Scheduler::current();

        if ($scheduler === null || !Scheduler::inCoroutine()) {
            throw new FiberException(
                'Channel::select() 需要在受调度器管理的协程内调用（Fibers::async() / Scheduler::go()）。'
            );
        }

        $suspension = $scheduler->park();

        /** @var array<int, array{0: self, 1: int}> $tickets */
        $tickets = [];

        foreach ($openChannels as $key => $channel) {
            $tickets[] = [$channel, $channel->registerSelectWaiter($suspension, $key)];
        }

        $timer = $timeout === null
            ? null
            : $scheduler->delay($timeout, static function () use ($suspension): void {
                $suspension->resume(null);
            });

        try {
            /** @var array{0: array-key, 1: mixed}|null $result */
            $result = $suspension->suspend();

            return $result;
        } finally {
            $timer?->cancel();

            foreach ($tickets as [$channel, $ticket]) {
                $channel->cancelWaiter($ticket);
            }
        }
    }

    /**
     * 关闭通道并唤醒所有等待者
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $senders = $this->senders;
        $receivers = $this->receivers;
        $this->senders = [];
        $this->receivers = [];

        foreach ($senders as $sender) {
            $sender['suspension']->resume(false);
        }

        foreach ($receivers as $receiver) {
            $receiver['suspension']->resume(
                $receiver['select'] === null ? null : [$receiver['select'], null]
            );
        }

        if ($this->name !== '' && isset(self::$channels[$this->name])) {
            unset(self::$channels[$this->name]);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * 缓冲区中待消费的消息数量
     */
    public function length(): int
    {
        return count($this->messages);
    }

    /**
     * 缓冲区容量
     */
    public function capacity(): int
    {
        return $this->bufferSize;
    }

    /**
     * 缓冲区是否已满（无缓冲通道恒为 true）
     */
    public function isFull(): bool
    {
        return count($this->messages) >= $this->bufferSize;
    }

    /**
     * 缓冲区是否为空
     */
    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    /**
     * 等待发送的协程数量
     */
    public function pendingSenders(): int
    {
        return count($this->senders);
    }

    /**
     * 等待接收的协程数量
     */
    public function pendingReceivers(): int
    {
        return count($this->receivers);
    }

    /**
     * 获取所有已登记的具名通道
     *
     * @return array<string, self>
     */
    public static function getActiveChannels(): array
    {
        return self::$channels;
    }

    /**
     * 关闭并清空所有具名通道（用于优雅退出与测试隔离）
     */
    public static function closeAll(): void
    {
        foreach (self::$channels as $channel) {
            $channel->close();
        }

        self::$channels = [];
    }

    /**
     * 登记一个 select 等待者
     *
     * @internal 仅供 {@see self::select()} 调用
     *
     * @param array-key $key
     */
    protected function registerSelectWaiter(Suspension $suspension, string|int $key): int
    {
        $id = ++$this->waiterSequence;
        $this->receivers[$id] = ['suspension' => $suspension, 'select' => $key];

        return $id;
    }

    /**
     * 注销等待者
     *
     * @internal
     */
    protected function cancelWaiter(int $id): void
    {
        unset($this->receivers[$id]);
    }

    /**
     * 把消息直接交给第一个仍在等待的接收者
     */
    private function deliverToReceiver(mixed $message): bool
    {
        while ($this->receivers !== []) {
            $key = array_key_first($this->receivers);
            $receiver = $this->receivers[$key];
            unset($this->receivers[$key]);

            if (!$receiver['suspension']->isPending()) {
                continue;
            }

            $receiver['suspension']->resume(
                $receiver['select'] === null ? $message : [$receiver['select'], $message]
            );

            return true;
        }

        return false;
    }

    /**
     * 缓冲区腾出空位后，把一个等待中的发送者的消息补进缓冲区并唤醒它
     *
     * 原实现只唤醒发送者却丢弃了它携带的消息，会造成静默丢数据。
     */
    private function promoteWaitingSender(): void
    {
        while ($this->senders !== []) {
            $key = array_key_first($this->senders);
            $sender = $this->senders[$key];
            unset($this->senders[$key]);

            if (!$sender['suspension']->isPending()) {
                continue;
            }

            $this->messages[] = $sender['message'];
            $sender['suspension']->resume(true);

            return;
        }
    }

    private function requireScheduler(string $operation): Scheduler
    {
        $scheduler = Scheduler::current();

        if ($scheduler === null || !Scheduler::inCoroutine()) {
            throw new FiberException(sprintf(
                'Channel::%s() 需要等待时必须运行在受调度器管理的协程内，'
                . '请使用 Fibers::async() / Fibers::loop() / Scheduler::go() 创建协程，'
                . '或改用非阻塞的 try%s()。',
                $operation,
                ucfirst($operation)
            ));
        }

        return $scheduler;
    }

    /**
     * 创建一个 MySQL 操作通道
     */
    public static function mysql(string $name, string $dsn, string $username = '', string $password = '', array $options = []): self
    {
        $channel = self::make($name, 10);

        Context::set("mysql.{$name}", new \PDO($dsn, $username, $password, $options));

        return $channel;
    }

    /**
     * 创建一个 Redis 操作通道
     */
    public static function redis(string $name, string $host = '127.0.0.1', int $port = 6379, string $password = '', int $database = 0): self
    {
        $channel = self::make($name, 10);

        $redis = new \Redis();
        $redis->connect($host, $port);

        if ($password !== '') {
            $redis->auth($password);
        }

        if ($database > 0) {
            $redis->select($database);
        }

        Context::set("redis.{$name}", $redis);

        return $channel;
    }

    /**
     * 创建一个 HTTP 请求通道
     */
    public static function http(string $name, array $options = []): self
    {
        $channel = self::make($name, 10);

        Context::set("http.{$name}", $options);

        return $channel;
    }
}
