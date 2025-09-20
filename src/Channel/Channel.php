<?php

namespace Nova\Fibers\Channel;

/**
 * Channel - 通信通道类
 * 
 * 实现纤程间通信，类似于Go语言的channel
 */
class Channel
{
    /**
     * @var string 通道名称
     */
    private string $name;
    
    /**
     * @var int 缓冲区大小
     */
    private int $bufferSize;
    
    /**
     * @var array 消息队列
     */
    private array $queue = [];
    
    /**
     * @var array 等待读取的纤程
     */
    private array $readers = [];
    
    /**
     * @var array 等待写入的纤程
     */
    private array $writers = [];
    
    /**
     * @var bool 通道是否已关闭
     */
    private bool $closed = false;
    
    /**
     * Channel 构造函数
     *
     * @param string $name 通道名称
     * @param int $bufferSize 缓冲区大小
     */
    public function __construct(string $name, int $bufferSize = 0)
    {
        $this->name = $name;
        $this->bufferSize = $bufferSize;
    }
    
    /**
     * 创建通道
     *
     * @param string $name 通道名称
     * @param int $bufferSize 缓冲区大小
     * @return self
     */
    public static function make(string $name, int $bufferSize = 0): self
    {
        return new self($name, $bufferSize);
    }
    
    /**
     * 向通道推送消息
     *
     * @param mixed $data 数据
     * @return bool 是否成功推送
     */
    public function push(mixed $data): bool
    {
        if ($this->closed) {
            return false;
        }
        
        // 如果缓冲区未满，直接添加到队列
        if (count($this->queue) < $this->bufferSize) {
            $this->queue[] = $data;
            return true;
        }
        
        // 如果缓冲区已满，需要等待
        // 由于我们还没有实现完整的纤程调度，这里只是示例
        echo "Channel {$this->name} buffer is full, waiting to push data\n";
        return false;
    }
    
    /**
     * 从通道弹出消息
     *
     * @param float $timeout 超时时间（秒）
     * @return mixed|null 数据或null（超时）
     */
    public function pop(float $timeout = 0): mixed
    {
        if ($this->closed && empty($this->queue)) {
            return null;
        }
        
        // 如果队列不为空，直接返回数据
        if (!empty($this->queue)) {
            return array_shift($this->queue);
        }
        
        // 如果队列为空，需要等待
        // 由于我们还没有实现完整的纤程调度，这里只是示例
        echo "Channel {$this->name} is empty, waiting to pop data\n";
        return null;
    }
    
    /**
     * 关闭通道
     *
     * @return void
     */
    public function close(): void
    {
        $this->closed = true;
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
     * 获取通道名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
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
     * 获取队列长度
     *
     * @return int
     */
    public function getQueueLength(): int
    {
        return count($this->queue);
    }
}
