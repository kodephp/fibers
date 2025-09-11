<?php

declare(strict_types=1);

namespace Nova\Fibers\Context;

/**
 * Fiber上下文管理类
 *
 * 提供类似Go context的上下文变量传递机制
 */
class Context
{
    /**
     * @var array<string, mixed> 上下文数据
     */
    private array $data = [];

    /**
     * @var self|null 父级上下文
     */
    private ?self $parent = null;

    /**
     * @var string 上下文ID
     */
    private string $id;

    /**
     * 构造函数
     *
     * @param string|null $id 上下文ID
     * @param self|null $parent 父级上下文
     */
    public function __construct(?string $id = null, ?self $parent = null)
    {
        $this->id = $id ?? uniqid('ctx_', true);
        $this->parent = $parent;
    }

    /**
     * 获取上下文ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 设置上下文值
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return self 返回新的上下文实例
     */
    public function withValue(string $key, mixed $value): self
    {
        $newContext = clone $this;
        $newContext->data[$key] = $value;
        return $newContext;
    }

    /**
     * 获取上下文值
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
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
     * @return array<string, mixed>
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
     * 检查是否存在指定键
     *
     * @param string $key 键名
     * @return bool
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
     * @param string|null $id 上下文ID
     * @return self
     */
    public function child(?string $id = null): self
    {
        return new self($id, $this);
    }
}
