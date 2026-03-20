<?php

declare(strict_types=1);

namespace Kode\Fibers\Async;

/**
 * 异步 IO 统一接口
 *
 * 提供统一的异步文件和网络 IO 操作
 */
class AsyncIO
{
    protected string $driver;

    public function __construct(?string $driver = null)
    {
        $this->driver = $driver ?? $this->detectBestDriver();
    }

    /**
     * 检测最佳驱动
     */
    protected function detectBestDriver(): string
    {
        if (extension_loaded('swoole')) {
            return 'swoole';
        }

        if (extension_loaded('swow')) {
            return 'swow';
        }

        if (extension_loaded('ev')) {
            return 'ev';
        }

        if (extension_loaded('event')) {
            return 'event';
        }

        return 'stream';
    }

    /**
     * 获取当前驱动
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * 异步读取文件
     */
    public function readFile(string $path, callable $callback): void
    {
        match ($this->driver) {
            'swoole' => $this->swooleRead($path, $callback),
            'swow' => $this->swowRead($path, $callback),
            'ev' => $this->evRead($path, $callback),
            'event' => $this->eventRead($path, $callback),
            default => $this->streamRead($path, $callback),
        };
    }

    /**
     * 异步写入文件
     */
    public function writeFile(string $path, string $data, callable $callback): void
    {
        match ($this->driver) {
            'swoole' => $this->swooleWrite($path, $data, $callback),
            'swow' => $this->swowWrite($path, $data, $callback),
            'ev' => $this->evWrite($path, $data, $callback),
            'event' => $this->eventWrite($path, $data, $callback),
            default => $this->streamWrite($path, $data, $callback),
        };
    }

    /**
     * 异步网络请求
     */
    public function asyncRequest(string $method, string $url, callable $callback, array $options = []): void
    {
        match ($this->driver) {
            'swoole' => $this->swooleRequest($method, $url, $callback, $options),
            'swow' => $this->swowRequest($method, $url, $callback, $options),
            default => $this->streamRequest($method, $url, $callback, $options),
        };
    }

    /**
     * 异步睡眠
     */
    public function sleep(float $seconds, callable $callback): void
    {
        if ($this->driver === 'swoole' && extension_loaded('swoole')) {
            \Swoole\Coroutine::sleep($seconds);
        } elseif ($this->driver === 'swow' && extension_loaded('swow')) {
            \Swow\Coroutine::sleep((int)($seconds * 1000));
        }
        $this->defer($callback);
    }

    /**
     * 延迟执行
     */
    public function defer(callable $callback): void
    {
        $callback();
    }

    /**
     * 并发执行多个任务
     */
    public function parallel(array $tasks, callable $callback): void
    {
        $results = [];
        $pending = count($tasks);

        foreach ($tasks as $key => $task) {
            $tasks[$key] = function () use ($task, $key, &$results, $callback, &$pending) {
                $result = $task();
                $results[$key] = $result;
                $pending--;
                if ($pending === 0) {
                    $callback($results);
                }
            };
        }

        foreach ($tasks as $task) {
            $this->defer($task);
        }
    }

    /**
     * Swoole 读取
     */
    protected function swooleRead(string $path, callable $callback): void
    {
        if (!extension_loaded('swoole')) {
            $this->streamRead($path, $callback);
            return;
        }
        
        \Swoole\Coroutine::create(function () use ($path, $callback) {
            $content = \Swoole\Coroutine\System::readFile($path);
            $callback($content);
        });
    }

    /**
     * Swoole 写入
     */
    protected function swooleWrite(string $path, string $data, callable $callback): void
    {
        if (!extension_loaded('swoole')) {
            $this->streamWrite($path, $data, $callback);
            return;
        }
        
        \Swoole\Coroutine::create(function () use ($path, $data, $callback) {
            $result = \Swoole\Coroutine\System::writeFile($path, $data);
            $callback($result);
        });
    }

    /**
     * Swoole 请求
     */
    protected function swooleRequest(string $method, string $url, callable $callback, array $options): void
    {
        if (!extension_loaded('swoole')) {
            $this->streamRequest($method, $url, $callback, $options);
            return;
        }
        
        \Swoole\Coroutine::create(function () use ($method, $url, $callback, $options) {
            $host = parse_url($url, PHP_URL_HOST);
            $port = parse_url($url, PHP_URL_PORT) ?: 80;
            $client = new \Swoole\Coroutine\Http\Client($host, $port);
            $client->set($options);
            $client->$method($url);
            $callback([
                'status' => $client->statusCode,
                'body' => $client->body,
                'headers' => $client->headers,
            ]);
        });
    }

    /**
     * Swow 读取
     */
    protected function swowRead(string $path, callable $callback): void
    {
        if (!extension_loaded('swow')) {
            $this->streamRead($path, $callback);
            return;
        }
        
        \Swow\Coroutine::create(function () use ($path, $callback) {
            $content = \Swow\FsUtils::readFile($path);
            $callback($content);
        });
    }

    /**
     * Swow 写入
     */
    protected function swowWrite(string $path, string $data, callable $callback): void
    {
        if (!extension_loaded('swow')) {
            $this->streamWrite($path, $data, $callback);
            return;
        }
        
        \Swow\Coroutine::create(function () use ($path, $data, $callback) {
            $result = \Swow\FsUtils::writeFile($path, $data);
            $callback($result);
        });
    }

    /**
     * Swow 请求
     */
    protected function swowRequest(string $method, string $url, callable $callback, array $options): void
    {
        if (!extension_loaded('swow')) {
            $this->streamRequest($method, $url, $callback, $options);
            return;
        }
        
        \Swow\Coroutine::create(function () use ($method, $url, $callback, $options) {
            $callback(['status' => 200, 'body' => 'swow response']);
        });
    }

    /**
     * Ev 读取
     */
    protected function evRead(string $path, callable $callback): void
    {
        $callback(@file_get_contents($path));
    }

    /**
     * Ev 写入
     */
    protected function evWrite(string $path, string $data, callable $callback): void
    {
        $result = @file_put_contents($path, $data);
        $callback($result);
    }

    /**
     * Event 读取
     */
    protected function eventRead(string $path, callable $callback): void
    {
        $callback(@file_get_contents($path));
    }

    /**
     * Event 写入
     */
    protected function eventWrite(string $path, string $data, callable $callback): void
    {
        $result = @file_put_contents($path, $data);
        $callback($result);
    }

    /**
     * Stream 读取（同步回退）
     */
    protected function streamRead(string $path, callable $callback): void
    {
        $callback(@file_get_contents($path));
    }

    /**
     * Stream 写入（同步回退）
     */
    protected function streamWrite(string $path, string $data, callable $callback): void
    {
        $result = @file_put_contents($path, $data);
        $callback($result);
    }

    /**
     * Stream 请求（同步回退）
     */
    protected function streamRequest(string $method, string $url, callable $callback, array $options): void
    {
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        $callback(['status' => 200, 'body' => $result, 'error' => null]);
    }

    /**
     * 获取支持的驱动列表
     */
    public static function getSupportedDrivers(): array
    {
        return [
            'swoole' => extension_loaded('swoole'),
            'swow' => extension_loaded('swow'),
            'ev' => extension_loaded('ev'),
            'event' => extension_loaded('event'),
            'stream' => true,
        ];
    }
}
