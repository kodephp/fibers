<?php

declare(strict_types=1);

namespace Nova\Fibers\Channel;

use Fiber;
use RuntimeException;

/**
 * Fiber 间通信通道（类似 Go Channel）
 *
 * @package Nova\Fibers\Channel
 */
class Channel
{
    /**
     * @var array 数据缓冲区
     */
    protected array $buffer = [];

    /**
     * @var int 缓冲区大小
     */
    protected int $bufferSize;

    /**
     * @var bool 通道是否已关闭
     */
    protected bool $closed = false;

    /**
     * @var Fiber[] 等待推送数据的纤程
     */
    protected array $pushWaiters = [];

    /**
     * @var Fiber[] 等待拉取数据的纤程
     */
    protected array $popWaiters = [];

    /**
     * Channel 构造函数
     *
     * @param int $bufferSize 缓冲区大小
     */
    public function __construct(int $bufferSize = 0)
    {
        $this->bufferSize = $bufferSize;
    }

    /**
     * 创建通道
     *
     * @param string $name 通道名称
     * @param int $bufferSize 缓冲区大小
     * @return static
     */
    public static function make(string $name, int $bufferSize = 0): self
    {
        return new static($bufferSize);
    }

    /**
     * 向通道推送数据
     *
     * @param mixed $data 数据
     * @return bool 是否成功
     */
    public function push(mixed $data): bool
    {
        if ($this->closed) {
            return false;
        }

        // 如果有等待拉取的纤程，直接传递数据
        if (!empty($this->popWaiters)) {
            $fiber = array_shift($this->popWaiters);
            $fiber->resume($data);
            return true;
        }

        // 如果缓冲区未满，将数据放入缓冲区
        if (count($this->buffer) < $this->bufferSize) {
            $this->buffer[] = $data;
            return true;
        }

        // 缓冲区已满，当前纤程挂起等待
        $this->pushWaiters[] = Fiber::getCurrent();
        Fiber::suspend();

        // 被唤醒后继续执行推送
        if ($this->closed) {
            return false;
        }

        $this->buffer[] = $data;
        return true;
    }

    /**
     * 从通道拉取数据
     *
     * @param float|null $timeout 超时时间（秒）
     * @return mixed|null 数据或 null（超时或通道关闭）
     */
    public function pop(?float $timeout = null): mixed
    {
        // 如果缓冲区有数据，直接返回
        if (!empty($this->buffer)) {
            $data = array_shift($this->buffer);

            // 唤醒等待推送的纤程
            if (!empty($this->pushWaiters)) {
                $fiber = array_shift($this->pushWaiters);
                $fiber->resume();
            }

            return $data;
        }

        // 如果通道已关闭，返回 null
        if ($this->closed) {
            return null;
        }

        // 如果有等待推送的纤程，唤醒它并等待数据
        if (!empty($this->pushWaiters)) {
            $fiber = array_shift($this->pushWaiters);
            $fiber->resume();
            // 等待推送纤程提供数据
            return Fiber::suspend();
        }

        // 没有数据可获取，当前纤程挂起等待
        $currentFiber = Fiber::getCurrent();
        $this->popWaiters[] = $currentFiber;

        if ($timeout !== null) {
            // 创建超时监控纤程
            $timeoutFiber = new Fiber(function () use ($currentFiber, $timeout) {
                usleep((int)($timeout * 1000000));
                if (in_array($currentFiber, $this->popWaiters, true)) {
                    $currentFiber->resume(null);
                }
            });
            $timeoutFiber->start();
        }

        $data = Fiber::suspend();

        // 移除等待列表中的当前纤程
        $index = array_search($currentFiber, $this->popWaiters, true);
        if ($index !== false) {
            unset($this->popWaiters[$index]);
        }

        return $data;
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

        // 唤醒所有等待的纤程
        foreach ($this->pushWaiters as $fiber) {
            $fiber->resume();
        }
        foreach ($this->popWaiters as $fiber) {
            $fiber->resume(null);
        }

        $this->pushWaiters = [];
        $this->popWaiters = [];
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
     * 获取缓冲区大小
     *
     * @return int
     */
    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    /**
     * 获取当前缓冲区中的元素数量
     *
     * @return int
     */
    public function getCount(): int
    {
        return count($this->buffer);
    }
}
