<?php

declare(strict_types=1);

namespace Nova\Fibers\Channel;

use Fiber;

/**
 * 纤程间通信通道
 * 
 * 提供类似于Go语言中channel的通信机制，支持在纤程间传递数据
 */
class Channel
{
    /**
     * 通道名称
     * 
     * @var mixed
     */
    protected mixed $name;

    /**
     * 通道缓冲区大小
     * 
     * @var int
     */
    protected int $bufferSize;

    /**
     * 通道数据队列
     * 
     * @var array
     */
    protected array $queue = [];

    /**
     * 等待读取数据的纤程队列
     * 
     * @var Fiber[]
     */
    protected array $readers = [];

    /**
     * 等待写入数据的纤程队列
     * 
     * @var Fiber[]
     */
    protected array $writers = [];

    /**
     * 通道是否已关闭
     * 
     * @var bool
     */
    protected bool $closed = false;

    /**
     * 构造函数
     * 
     * @param mixed $name 通道名称
     * @param int $bufferSize 缓冲区大小
     */
    public function __construct(mixed $name, int $bufferSize = 0)
    {
        $this->name = $name;
        $this->bufferSize = $bufferSize;
    }

    /**
     * 创建一个新的通道实例
     * 
     * @param mixed $name 通道名称
     * @param int $bufferSize 缓冲区大小
     * @return static 通道实例
     */
    public static function make(mixed $name, int $bufferSize = 0): static
    {
        return new static($name, $bufferSize);
    }

    /**
     * 向通道推送数据
     * 
     * @param mixed $data 要推送的数据
     * @param float|null $timeout 超时时间（秒）
     * @return bool 推送是否成功
     */
    public function push(mixed $data, ?float $timeout = null): bool
    {
        // 如果通道已关闭，无法推送数据
        if ($this->closed) {
            return false;
        }

        // 如果缓冲区未满，直接添加数据
        if (count($this->queue) < $this->bufferSize) {
            $this->queue[] = $data;
            
            // 如果有等待读取的纤程，唤醒其中一个
            if (!empty($this->readers)) {
                $reader = array_shift($this->readers);
                $reader->resume();
            }
            
            return true;
        }

        // 如果没有缓冲区或缓冲区已满，需要等待
        $currentFiber = Fiber::getCurrent();
        
        // 如果不在纤程中，直接返回失败
        if ($currentFiber === null) {
            return false;
        }
        
        // 如果没有设置超时，直接挂起当前纤程
        if ($timeout === null) {
            $this->writers[] = $currentFiber;
            $result = Fiber::suspend();
            
            // 如果通道在等待期间关闭，返回失败
            if ($this->closed) {
                return false;
            }
            
            // 将数据添加到队列
            $this->queue[] = $data;
            return true;
        }

        // 如果设置了超时，需要在超时后返回
        $startTime = microtime(true);
        $this->writers[] = $currentFiber;
        
        try {
            $result = Fiber::suspend();
        } catch (\Throwable $e) {
            // 如果在等待期间发生异常，从等待队列中移除当前纤程
            $key = array_search($currentFiber, $this->writers, true);
            if ($key !== false) {
                unset($this->writers[$key]);
            }
            throw $e;
        }
        
        // 检查是否超时
        if (microtime(true) - $startTime >= $timeout) {
            // 从等待队列中移除当前纤程
            $key = array_search($currentFiber, $this->writers, true);
            if ($key !== false) {
                unset($this->writers[$key]);
            }
            return false;
        }
        
        // 如果通道在等待期间关闭，返回失败
        if ($this->closed) {
            return false;
        }
        
        // 将数据添加到队列
        $this->queue[] = $data;
        return true;
    }

    /**
     * 从通道弹出数据
     * 
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 从通道获取的数据，如果超时或通道关闭则返回false
     */
    public function pop(?float $timeout = null): mixed
    {
        // 如果队列中有数据，直接返回
        if (!empty($this->queue)) {
            $data = array_shift($this->queue);
            
            // 如果有等待写入的纤程，唤醒其中一个
            if (!empty($this->writers)) {
                $writer = array_shift($this->writers);
                $writer->resume();
            }
            
            return $data;
        }

        // 如果通道已关闭且队列为空，返回false
        if ($this->closed) {
            return false;
        }

        // 如果队列为空，需要等待数据
        $currentFiber = Fiber::getCurrent();
        
        // 如果不在纤程中，直接返回false
        if ($currentFiber === null) {
            return false;
        }
        
        // 如果没有设置超时，直接挂起当前纤程
        if ($timeout === null) {
            $this->readers[] = $currentFiber;
            $data = Fiber::suspend();
            
            // 如果通道在等待期间关闭，返回false
            if ($this->closed) {
                return false;
            }
            
            return $data;
        }

        // 如果设置了超时，需要在超时后返回
        $startTime = microtime(true);
        $this->readers[] = $currentFiber;
        
        try {
            $data = Fiber::suspend();
        } catch (\Throwable $e) {
            // 如果在等待期间发生异常，从等待队列中移除当前纤程
            $key = array_search($currentFiber, $this->readers, true);
            if ($key !== false) {
                unset($this->readers[$key]);
            }
            throw $e;
        }
        
        // 检查是否超时
        if (microtime(true) - $startTime >= $timeout) {
            // 从等待队列中移除当前纤程
            $key = array_search($currentFiber, $this->readers, true);
            if ($key !== false) {
                unset($this->readers[$key]);
            }
            return false;
        }
        
        // 如果通道在等待期间关闭，返回false
        if ($this->closed) {
            return false;
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
        $this->closed = true;
        
        // 唤醒所有等待读取的纤程
        foreach ($this->readers as $reader) {
            if ($reader !== null) {
                $reader->resume(false);
            }
        }
        
        // 唤醒所有等待写入的纤程
        foreach ($this->writers as $writer) {
            if ($writer !== null) {
                $writer->resume(false);
            }
        }
        
        // 清空等待队列
        $this->readers = [];
        $this->writers = [];
    }

    /**
     * 检查通道是否已关闭
     * 
     * @return bool 通道是否已关闭
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * 获取通道名称
     * 
     * @return string 通道名称
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取通道缓冲区大小
     * 
     * @return int 缓冲区大小
     */
    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    /**
     * 获取通道中当前数据数量
     * 
     * @return int 数据数量
     */
    public function getCount(): int
    {
        return count($this->queue);
    }
}
