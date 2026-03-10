<?php

if (!function_exists('env')) {
    /**
     * 获取环境变量值
     *
     * @param string $key 环境变量键名
     * @param mixed $default 默认值
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // 处理布尔值
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
        
        // 处理引号
        if (strlen($value) > 1 && substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
            return substr($value, 1, -1);
        }
        
        return $value;
    }
}

use Kode\Fibers\Fibers;
use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Support\CpuInfo;

if (!function_exists('fiber_run')) {
    /**
     * Run a task in a fiber
     *
     * @param callable $task
     * @param float|null $timeout
     * @return mixed
     */
    function fiber_run(callable $task, ?float $timeout = null)
    {
        return Fibers::run($task, $timeout);
    }
}

if (!function_exists('fiber_pool')) {
    /**
     * Create a fiber pool
     *
     * @param array $options
     * @return FiberPool
     */
    function fiber_pool(array $options = [])
    {
        return Fibers::pool($options);
    }
}

if (!function_exists('fiber_channel')) {
    /**
     * Create a channel
     *
     * @param string $name
     * @param int $buffer
     * @return Channel
     */
    function fiber_channel(string $name, int $buffer = 0)
    {
        return Fibers::channel($name, $buffer);
    }
}

if (!function_exists('fiber_cpu_count')) {
    /**
     * Get CPU count
     *
     * @return int
     */
    function fiber_cpu_count()
    {
        return Fibers::cpuCount();
    }
}

if (!function_exists('fiber_go')) {
    function fiber_go(callable $task, ?float $timeout = null)
    {
        return Fibers::go($task, $timeout);
    }
}

if (!function_exists('fiber_with_context')) {
    function fiber_with_context(array $context, callable $task, ?float $timeout = null)
    {
        return Fibers::withContext($context, $task, $timeout);
    }
}

if (!function_exists('fiber_batch')) {
    function fiber_batch(array $items, callable $handler, ?int $concurrency = null, ?float $timeout = null): array
    {
        return Fibers::batch($items, $handler, $concurrency, $timeout);
    }
}

if (!function_exists('fiber_runtime_features')) {
    function fiber_runtime_features(): array
    {
        return Fibers::runtimeFeatures();
    }
}

if (!function_exists('fiber_roadmap')) {
    function fiber_roadmap(): array
    {
        return Fibers::roadmap();
    }
}

if (!function_exists('fiber_resilient_batch')) {
    function fiber_resilient_batch(array $items, callable $handler, array $options = []): array
    {
        return Fibers::resilientBatch($items, $handler, $options);
    }
}

if (!function_exists('fiber_schedule_distributed')) {
    function fiber_schedule_distributed(array $tasks, array $nodes = []): array
    {
        return Fibers::scheduleDistributed($tasks, $nodes);
    }
}

if (!function_exists('fiber_schedule_distributed_advanced')) {
    function fiber_schedule_distributed_advanced(array $tasks, array $nodes = [], array $options = []): array
    {
        return Fibers::scheduleDistributedAdvanced($tasks, $nodes, $options);
    }
}

if (!function_exists('fiber_resilient_run')) {
    function fiber_resilient_run(callable $task, array $options = [])
    {
        return Fibers::resilientRun($task, $options);
    }
}

if (!function_exists('fiber_concurrent_with_context')) {
    function fiber_concurrent_with_context(array $context, array $tasks, ?float $timeout = null): array
    {
        return Fibers::concurrentWithContext($context, $tasks, $timeout);
    }
}

if (!function_exists('fiber_schedule_distributed_remote')) {
    function fiber_schedule_distributed_remote(array $tasks, array $nodes = [], $transport = null): array
    {
        return Fibers::scheduleDistributedRemote($tasks, $nodes, $transport);
    }
}

if (!function_exists('fiber_runtime_bridge_info')) {
    function fiber_runtime_bridge_info(): array
    {
        return Fibers::runtimeBridgeInfo();
    }
}

if (!function_exists('fiber_run_on_bridge')) {
    function fiber_run_on_bridge(callable $task, ?string $preferred = null)
    {
        return Fibers::runOnBridge($task, $preferred);
    }
}

if (!function_exists('fiber_profile')) {
    function fiber_profile(callable $task, string $name = 'task'): array
    {
        return Fibers::profile($task, $name);
    }
}
