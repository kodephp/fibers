<?php

declare(strict_types=1);

namespace Nova\Fibers\Context;

/**
 * 上下文类
 * 
 * 提供类似Go语言的上下文机制，用于在纤程间传递请求范围的值
 */
class Context
{
    /**
     * 上下文数据
     * 
     * @var array
     */
    private array $data = [];

    /**
     * 父级上下文
     * 
     * @var Context|null
     */
    private ?Context $parent;

    /**
     * 上下文ID
     * 
     * @var string
     */
    private string $id;

    /**
     * 构造上下文
     * 
     * @param string $id 上下文ID
     * @param Context|null $parent 父级上下文
     */
    public function __construct(string $id, ?Context $parent = null)
    {
        $this->id = $id;
        $this->parent = $parent;
    }

    /**
     * 获取上下文ID
     * 
     * @return string 上下文ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 创建带有值的新上下文
     * 
     * @param string $key 键
     * @param mixed $value 值
     * @return Context 新上下文
     */
    public function withValue(string $key, mixed $value): Context
    {
        $context = new static($this->id, $this);
        $context->data[$key] = $value;
        return $context;
    }

    /**
     * 获取上下文中的值
     * 
     * @param string $key 键
     * @param mixed $default 默认值
     * @return mixed 值
     */
    public function value(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        if ($this->parent !== null) {
            return $this->parent->value($key, $default);
        }

        return $default;
    }

    /**
     * 获取所有上下文数据
     * 
     * @return array 所有数据
     */
    public function all(): array
    {
        $data = $this->data;
        if ($this->parent !== null) {
            $data = array_merge($this->parent->all(), $data);
        }
        return $data;
    }

    /**
     * 检查上下文中是否存在指定键
     * 
     * @param string $key 键
     * @return bool 是否存在
     */
    public function has(string $key): bool
    {
        if (array_key_exists($key, $this->data)) {
            return true;
        }

        if ($this->parent !== null) {
            return $this->parent->has($key);
        }

        return false;
    }

    /**
     * 创建子上下文
     * 
     * @param string|null $id 子上下文ID，如果为null则自动生成
     * @return Context 子上下文
     */
    public function child(?string $id = null): Context
    {
        if ($id === null) {
            $id = uniqid($this->id . '-', true);
        }
        return new static($id, $this);
    }
}
