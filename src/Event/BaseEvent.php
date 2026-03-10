<?php
declare(strict_types=1);

namespace Kode\Fibers\Event;

/**
 * 基础事件类 - 实现Event接口
 */
class BaseEvent implements Event
{
    /**
     * 事件名称
     *
     * @var string
     */
    protected string $name;

    /**
     * 事件数据
     *
     * @var mixed
     */
    protected mixed $data;

    /**
     * 事件时间戳
     *
     * @var int
     */
    protected int $timestamp;

    /**
     * 事件上下文数据
     *
     * @var array
     */
    protected array $context = [];

    /**
     * 事件传播状态
     *
     * @var bool
     */
    protected bool $propagationStopped = false;

    /**
     * 构造函数
     *
     * @param string $name 事件名称
     * @param mixed $data 事件数据
     * @param array $context 上下文数据
     */
    public function __construct(string $name, mixed $data = null, array $context = [])
    {
        $this->name = $name;
        $this->data = $data;
        $this->context = $context;
        $this->timestamp = time();
    }

    /**
     * 获取事件名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取事件数据
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * 设置事件数据
     *
     * @param mixed $data 事件数据
     * @return self
     */
    public function setData(mixed $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 获取事件时间戳
     *
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * 获取事件上下文数据
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 设置事件上下文数据
     *
     * @param array $context 上下文数据
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * 获取指定的上下文数据
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * 设置指定的上下文数据
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return self
     */
    public function setContextValue(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * 停止事件传播
     *
     * @return void
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * 检查事件传播是否已停止
     *
     * @return bool
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * 检查是否包含特定的上下文键
     *
     * @param string $key 键名
     * @return bool
     */
    public function hasContextKey(string $key): bool
    {
        return array_key_exists($key, $this->context);
    }

    /**
     * 获取事件的详细信息
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
            'context' => $this->context,
            'propagation_stopped' => $this->propagationStopped,
        ];
    }

    /**
     * 转换为JSON字符串
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * 从数组创建事件实例
     *
     * @param array $data 事件数据数组
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $event = new self(
            $data['name'] ?? '',
            $data['data'] ?? null,
            $data['context'] ?? []
        );

        // 如果有自定义时间戳
        if (isset($data['timestamp'])) {
            $event->timestamp = $data['timestamp'];
        }

        // 如果有传播状态
        if (isset($data['propagation_stopped']) && $data['propagation_stopped']) {
            $event->stopPropagation();
        }

        return $event;
    }

    /**
     * 从JSON字符串创建事件实例
     *
     * @param string $json JSON字符串
     * @return self
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return self::fromArray($data);
    }

    /**
     * 魔术方法 - 获取属性
     *
     * @param string $name 属性名
     * @return mixed
     */
    public function __get(string $name)
    {
        if ($name === 'name') {
            return $this->name;
        }
        if ($name === 'data') {
            return $this->data;
        }
        if ($name === 'timestamp') {
            return $this->timestamp;
        }
        if ($name === 'context') {
            return $this->context;
        }
        if ($name === 'propagationStopped') {
            return $this->propagationStopped;
        }

        // 尝试从上下文获取
        if (isset($this->context[$name])) {
            return $this->context[$name];
        }

        return null;
    }

    /**
     * 魔术方法 - 设置属性
     *
     * @param string $name 属性名
     * @param mixed $value 属性值
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        if ($name === 'name') {
            $this->name = $value;
        } elseif ($name === 'data') {
            $this->data = $value;
        } elseif ($name === 'context') {
            $this->context = $value;
        } else {
            // 设置到上下文
            $this->context[$name] = $value;
        }
    }

    /**
     * 魔术方法 - 检查属性是否存在
     *
     * @param string $name 属性名
     * @return bool
     */
    public function __isset(string $name): bool
    {
        if ($name === 'name' || $name === 'data' || $name === 'timestamp' || $name === 'context' || $name === 'propagationStopped') {
            return true;
        }

        return isset($this->context[$name]);
    }

    /**
     * 魔术方法 - 调用不存在的方法
     *
     * @param string $name 方法名
     * @param array $arguments 参数
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        // 支持getXxx格式的方法
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            $key = strtolower(substr($name, 3, 1)) . substr($name, 4);
            return $this->getContextValue($key, ...$arguments);
        }

        // 支持setXxx格式的方法
        if (str_starts_with($name, 'set') && strlen($name) > 3 && count($arguments) > 0) {
            $key = strtolower(substr($name, 3, 1)) . substr($name, 4);
            $this->setContextValue($key, $arguments[0]);
            return $this;
        }

        throw new \BadMethodCallException(sprintf('Method %s::%s does not exist', static::class, $name));
    }
}