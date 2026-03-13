<?php

declare(strict_types=1);

namespace Kode\Fibers\HttpClient;

use Kode\Fibers\Attributes\FiberSafe;
use Kode\Fibers\Attributes\Timeout;
use Kode\Fibers\Fibers;
use Kode\Fibers\Exceptions\FiberException;
use Kode\HttpClient\Context\Context as HttpContext;
use Kode\HttpClient\HttpClientInterface as BaseHttpClient;
use Kode\HttpClient\HttpClient as NativeHttpClient;
use Kode\HttpClient\Driver\CurlDriver;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Fiber-safe HTTP client
 *
 * This client provides fiber-aware HTTP request functionality with support
 * for concurrent requests and non-blocking operations.
 *
 * @method Response get(string $url, array $headers = [], array $options = [])
 * @method Response post(string $url, mixed $data = [], array $headers = [], array $options = [])
 * @method Response put(string $url, mixed $data = [], array $headers = [], array $options = [])
 * @method Response delete(string $url, array $headers = [], array $options = [])
 * @method Response patch(string $url, mixed $data = [], array $headers = [], array $options = [])
 * @method Response head(string $url, array $headers = [], array $options = [])
 * @method Response options(string $url, array $headers = [], array $options = [])
 * @method array concurrent(array $requests, int $concurrency = 5, float $timeout = 30)
 */
class HttpClient
{
    /**
     * 底层 HTTP 客户端实例
     */
    protected BaseHttpClient $client;

    /**
     * 客户端初始化选项
     */
    protected array $clientOptions = [];

    /**
     * 创建 Fiber 安全 HTTP 客户端
     */
    public function __construct(array $options = [], ?BaseHttpClient $client = null)
    {
        $this->checkEnvironment();
        $this->clientOptions = $options;
        $this->client = $client ?? new NativeHttpClient(new CurlDriver());
    }

    /**
     * 工厂方法
     */
    public static function make(array $options = []): static
    {
        return new static($options);
    }

    /**
     * 检查运行环境
     */
    protected function checkEnvironment(): void
    {
        if (PHP_VERSION_ID < 80100) {
            throw new FiberException('HTTP client requires PHP 8.1 or above.');
        }

        $disabledFunctions = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        if (in_array('curl_init', $disabledFunctions, true) && !class_exists('Amp\\Http\\Client\\HttpClient')) {
            throw new FiberException('curl_init is disabled and no Amp HTTP client implementation is available.');
        }
    }

    /**
     * 获取运行环境信息
     */
    public function getEnvironmentInfo(): array
    {
        return [
            'curl_available' => function_exists('curl_init'),
            'curl_version' => function_exists('curl_version') ? curl_version() : null,
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'disabled_functions' => explode(',', ini_get('disable_functions')),
        ];
    }

    /**
     * 发起 GET 请求
     */
    #[FiberSafe]
    public function get(string $url, array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('GET', $url, [], $headers, $options);
    }

    /**
     * 发起 POST 请求
     */
    #[FiberSafe]
    public function post(string $url, mixed $data = [], array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('POST', $url, $data, $headers, $options);
    }

    /**
     * 发起 PUT 请求
     */
    #[FiberSafe]
    public function put(string $url, mixed $data = [], array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('PUT', $url, $data, $headers, $options);
    }

    /**
     * 发起 DELETE 请求
     */
    #[FiberSafe]
    public function delete(string $url, array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('DELETE', $url, [], $headers, $options);
    }

    /**
     * 发起 PATCH 请求
     */
    #[FiberSafe]
    public function patch(string $url, mixed $data = [], array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('PATCH', $url, $data, $headers, $options);
    }

    /**
     * 发起 HEAD 请求
     */
    #[FiberSafe]
    public function head(string $url, array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('HEAD', $url, [], $headers, $options);
    }

    /**
     * 发起 OPTIONS 请求
     */
    #[FiberSafe]
    public function options(string $url, array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('OPTIONS', $url, [], $headers, $options);
    }

    /**
     * 发起 HTTP 请求
     */
    #[FiberSafe]
    #[Timeout(30)]
    public function send(string $method, string $url, mixed $data = [], array $headers = [], array $options = []): ResponseInterface
    {
        try {
            $method = strtoupper($method);
            $normalizedHeaders = $this->normalizeHeaders($headers);

            $body = null;
            if ($data !== [] && $data !== null) {
                $normalizedHeaders['Content-Type'] = $this->detectContentType($data);
                $body = $normalizedHeaders['Content-Type'] === 'application/json'
                    ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : (is_array($data) ? http_build_query($data) : (string) $data);
            }

            $request = new Psr7Request($method, $url, $normalizedHeaders, $body);
            $this->applyContext($options);

            return $this->client->sendRequest($request);
        } catch (\Throwable $e) {
            throw new FiberException('HTTP request failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * 规范化请求头
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        
        foreach ($headers as $key => $value) {
            $normalizedKey = str_replace('_', '-', ucwords(strtolower($key), '_-'));
            $normalized[$normalizedKey] = $value;
        }
        
        return $normalized;
    }

    /**
     * 根据负载推断内容类型
     */
    protected function detectContentType(mixed $data): string
    {
        if (is_array($data) || $data instanceof \JsonSerializable || $data instanceof \stdClass) {
            return 'application/json';
        }
        
        return 'application/x-www-form-urlencoded';
    }

    /**
     * 并发发起多个 HTTP 请求
     */
    #[FiberSafe]
    public function concurrent(array $requests, int $concurrency = 5, float $timeout = 30, bool $failOnError = true): array
    {
        $concurrency = max(1, min($concurrency, count($requests)));

        $results = [];
        $errors = [];
        $chunks = array_chunk($requests, $concurrency, true);

        foreach ($chunks as $chunk) {
            $tasks = [];
            foreach ($chunk as $key => $request) {
                $tasks[$key] = function () use ($request) {
                    $method = strtoupper($request['method'] ?? 'GET');
                    $url = (string) ($request['url'] ?? '');
                    if ($url === '') {
                        throw new FiberException('Request url is required.');
                    }

                    $data = $request['data'] ?? [];
                    $headers = $request['headers'] ?? [];
                    $options = $request['options'] ?? [];
                    if (isset($request['timeout'])) {
                        $options['timeout'] = (float) $request['timeout'];
                    }

                    return $this->send($method, $url, $data, $headers, $options);
                };
            }

            $batchResults = Fibers::withTimeout(
                fn() => Fibers::parallel($tasks),
                $timeout
            );

            foreach ($batchResults as $key => $response) {
                if ($response instanceof \Throwable) {
                    $errors[$key] = $response;
                    continue;
                }
                $results[$key] = $response;
            }
        }

        if (!empty($errors) && $failOnError) {
            $errorMessages = [];
            foreach ($errors as $key => $error) {
                $errorMessages[] = "Request {$key}: {$error->getMessage()}";
            }
            throw new FiberException('Some HTTP requests failed: ' . implode(', ', $errorMessages));
        }

        return [
            'results' => $results,
            'errors' => $errors,
            'success_count' => count($results),
            'error_count' => count($errors)
        ];
    }

    /**
     * 应用请求上下文配置
     */
    protected function applyContext(array $options): void
    {
        if (isset($options['timeout'])) {
            HttpContext::setTimeout((float) $options['timeout']);
        }
    }

    /**
     * 获取底层 HTTP 客户端
     */
    public function getClient(): BaseHttpClient
    {
        return $this->client;
    }
}
