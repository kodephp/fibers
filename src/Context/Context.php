<?php

namespace Nova\Fibers\Context;

/**
 * Context - 上下文管理实现
 * 
 * 提供键值对存储的上下文，支持继承和取消
 */
class Context
{
    /**
     * @var array 上下文数据
     */
    private array $values = [];

    /**
     * @var Context|null 父级上下文
     */
    private ?Context $parent;

    /**
     * @var bool 上下文是否已取消
     */
    private bool $cancelled = false;

    /**
     * @var string|null 取消原因
     */
    private ?string $cancelReason = null;

    /**
     * 构造函数
     *
     * @param Context|null $parent 父级上下文
     */
    public function __construct(?Context $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * 设置上下文值
     *
     * @param string $key 键
     * @param mixed $value 值
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        if ($this->cancelled) {
            throw new \RuntimeException("Cannot set value on cancelled context");
        }
        
        $this->values[$key] = $value;
    }

    /**
     * 获取上下文值
     *
     * @param string $key 键
     * @return mixed 值
     */
    public function get(string $key): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }
        
        if ($this->parent) {
            return $this->parent->get($key);
        }
        
        return null;
    }

    /**
     * 检查上下文是否包含指定键
     *
     * @param string $key 键
     * @return bool 是否包含
     */
    public function has(string $key): bool
    {
        if (array_key_exists($key, $this->values)) {
            return true;
        }
        
        if ($this->parent) {
            return $this->parent->has($key);
        }
        
        return false;
    }

    /**
     * 取消上下文
     *
     * @param string|null $reason 取消原因
     * @return void
     */
    public function cancel(?string $reason = null): void
    {
        $this->cancelled = true;
        $this->cancelReason = $reason;
        
        // 取消所有子上下文
        // 注意：这里需要在ContextManager中实现子上下文的管理
    }

    /**
     * 检查上下文是否已取消
     *
     * @return bool 是否已取消
     */
    public function isCancelled(): bool
    {
        if ($this->cancelled) {
            return true;
        }
        
        if ($this->parent) {
            return $this->parent->isCancelled();
        }
        
        return false;
    }

    /**
     * 获取取消原因
     *
     * @return string|null 取消原因
     */
    public function getCancelReason(): ?string
    {
        if ($this->cancelled) {
            return $this->cancelReason;
        }
        
        if ($this->parent) {
            return $this->parent->getCancelReason();
        }
        
        return null;
    }

    /**
     * 派生新的上下文
     *
     * @return Context 新的上下文
     */
    public function derive(): Context
    {
        if ($this->cancelled) {
            throw new \RuntimeException("Cannot derive from cancelled context");
        }
        
        return new Context($this);
    }
}
