<?php

declare(strict_types=1);

namespace Nova\Fibers\Support;

use Closure;
use Psr\Container\ContainerInterface;
use Nova\Fibers\Exceptions\FiberException;

/**
 * 依赖注入容器
 * 
 * 简单的依赖注入容器实现
 */
class Container implements ContainerInterface
{
    /**
     * 存储绑定的定义
     * 
     * @var array
     */
    private array $bindings = [];

    /**
     * 存储已解析的实例
     * 
     * @var array
     */
    private array $instances = [];

    /**
     * 绑定一个抽象到容器
     * 
     * @param string $abstract 抽象名称
     * @param Closure|string|null $concrete 具体实现
     * @param bool $shared 是否共享实例
     * @return void
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        
        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    /**
     * 绑定一个共享实例到容器
     * 
     * @param string $abstract 抽象名称
     * @param Closure|string|null $concrete 具体实现
     * @return void
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * 解析给定的抽象类型
     * 
     * @param string $abstract 抽象名称
     * @return mixed 解析后的实例
     * @throws FiberException
     */
    public function make(string $abstract): mixed
    {
        // 如果已经存在共享实例，直接返回
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        // 获取绑定信息
        $binding = $this->bindings[$abstract] ?? [];
        
        if (empty($binding)) {
            // 如果没有绑定，尝试直接创建类实例
            return $this->build($abstract);
        }
        
        $concrete = $binding['concrete'];
        $shared = $binding['shared'];
        
        // 解析具体实现
        $object = $this->resolve($concrete);
        
        // 如果是共享实例，保存起来
        if ($shared) {
            $this->instances[$abstract] = $object;
        }
        
        return $object;
    }

    /**
     * 解析具体实现
     * 
     * @param Closure|string $concrete 具体实现
     * @return mixed 解析后的实例
     * @throws FiberException
     */
    private function resolve(Closure|string $concrete): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }
        
        return $this->build($concrete);
    }

    /**
     * 构建类实例
     * 
     * @param string $className 类名
     * @return object 类实例
     * @throws FiberException
     */
    private function build(string $className): object
    {
        // 检查类是否存在
        if (!class_exists($className)) {
            throw new FiberException("Class {$className} not found");
        }
        
        // 获取类的反射对象
        $reflector = new \ReflectionClass($className);
        
        // 如果类无法实例化（如抽象类），抛出异常
        if (!$reflector->isInstantiable()) {
            throw new FiberException("Class {$className} is not instantiable");
        }
        
        // 获取构造函数
        $constructor = $reflector->getConstructor();
        
        // 如果没有构造函数，直接创建实例
        if ($constructor === null) {
            return new $className();
        }
        
        // 获取构造函数参数
        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);
        
        // 创建实例
        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * 解析依赖
     * 
     * @param array $parameters 参数数组
     * @return array 依赖数组
     * @throws FiberException
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            // 获取参数类型
            $type = $parameter->getType();
            
            // 如果没有类型提示，检查是否有默认值
            if ($type === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new FiberException("Cannot resolve parameter {$parameter->getName()}");
                }
                continue;
            }
            
            // 获取类型名称
            $typeName = $type->getName();
            
            // 如果是内置类型且有默认值，使用默认值
            if ($type->isBuiltin() && $parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }
            
            // 解析依赖
            $dependencies[] = $this->make($typeName);
        }
        
        return $dependencies;
    }

    /**
     * 检查容器中是否存在给定的抽象
     * 
     * @param string $id 抽象名称
     * @return bool 是否存在
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || class_exists($id);
    }

    /**
     * 获取给定的抽象
     * 
     * @param string $id 抽象名称
     * @return mixed 解析后的实例
     * @throws FiberException
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new FiberException("Class {$id} is not bound in container");
        }
        
        return $this->make($id);
    }
}