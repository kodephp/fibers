<?php

declare(strict_types=1);

namespace Kode\Fibers\HttpClient;

use Kode\Fibers\Attributes\FiberSafe;
use Kode\Fibers\Attributes\Timeout;
use Kode\Fibers\Fibers;
use Kode\Fibers\Exceptions\FiberException;
use Psr\Http\Message\ResponseInterface;

/**
 * Fiber-safe HTTP client
 *
 * 自动降级机制：
 * - 已安装 kode/http-client + guzzlehttp/psr7：使用完整功能
 * - 未安装：使用基础 cURL 实现
 */
class HttpClient
{
    protected $client;
    protected array $clientOptions = [];
    protected bool $useNativeDriver = false;

    public function __construct(array $options = [], $client = null)
    {
        $this->clientOptions = $options;
        
        if ($client !== null) {
            $this->client = $client;
            return;
        }
        
        if ($this->hasFullDependencies()) {
            $this->client = new \Kode\HttpClient\HttpClient(new \Kode\HttpClient\Driver\CurlDriver());
        } else {
            $this->useNativeDriver = true;
        }
    }

    protected function hasFullDependencies(): bool
    {
        return class_exists(\Kode\HttpClient\HttpClient::class) 
            && class_exists(\GuzzleHttp\Psr7\Request::class);
    }

    public static function make(array $options = []): static
    {
        return new static($options);
    }

    public function isUsingNativeDriver(): bool
    {
        return $this->useNativeDriver;
    }

    public function getEnvironmentInfo(): array
    {
        return [
            'curl_available' => function_exists('curl_init'),
            'curl_version' => function_exists('curl_version') ? curl_version() : null,
            'has_http_client_package' => class_exists(\Kode\HttpClient\HttpClient::class),
            'has_psr7_package' => class_exists(\GuzzleHttp\Psr7\Request::class),
            'using_native_driver' => $this->useNativeDriver,
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
        ];
    }

    #[FiberSafe]
    public function get(string $url, array $headers = [], array $options = []): Response
    {
        return $this->send('GET', $url, [], $headers, $options);
    }

    #[FiberSafe]
    public function post(string $url, mixed $data = [], array $headers = [], array $options = []): Response
    {
        return $this->send('POST', $url, $data, $headers, $options);
    }

    #[FiberSafe]
    public function put(string $url, mixed $data = [], array $headers = [], array $options = []): Response
    {
        return $this->send('PUT', $url, $data, $headers, $options);
    }

    #[FiberSafe]
    public function delete(string $url, array $headers = [], array $options = []): Response
    {
        return $this->send('DELETE', $url, [], $headers, $options);
    }

    #[FiberSafe]
    public function patch(string $url, mixed $data = [], array $headers = [], array $options = []): Response
    {
        return $this->send('PATCH', $url, $data, $headers, $options);
    }

    #[FiberSafe]
    public function head(string $url, array $headers = [], array $options = []): Response
    {
        return $this->send('HEAD', $url, [], $headers, $options);
    }

    #[FiberSafe]
    public function options(string $url, array $headers = [], array $options = []): Response
    {
        return $this->send('OPTIONS', $url, [], $headers, $options);
    }

    #[FiberSafe]
    #[Timeout(30)]
    public function send(string $method, string $url, mixed $data = [], array $headers = [], array $options = []): Response
    {
        if ($this->useNativeDriver) {
            return $this->sendNative($method, $url, $data, $headers, $options);
        }
        
        return $this->sendWithPackage($method, $url, $data, $headers, $options);
    }

    protected function sendWithPackage(string $method, string $url, mixed $data, array $headers, array $options = []): Response
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

            $request = new \GuzzleHttp\Psr7\Request($method, $url, $normalizedHeaders, $body);
            $psrResponse = $this->client->sendRequest($request);
            
            return new Response(
                $psrResponse->getStatusCode(),
                $psrResponse->getHeaders(),
                (string) $psrResponse->getBody()
            );
        } catch (\Throwable $e) {
            throw new FiberException('HTTP request failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    protected function sendNative(string $method, string $url, mixed $data, array $headers, array $options = []): Response
    {
        if (!function_exists('curl_init')) {
            throw new FiberException('cURL extension is required for native HTTP client. Install ext-curl or run: composer require kode/http-client guzzlehttp/psr7');
        }

        $ch = curl_init();
        
        $method = strtoupper($method);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout'] ?? 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        $curlHeaders = [];
        $normalizedHeaders = $this->normalizeHeaders($headers);
        
        if ($data !== [] && $data !== null && !in_array($method, ['GET', 'HEAD'])) {
            $normalizedHeaders['Content-Type'] = $this->detectContentType($data);
            $body = $normalizedHeaders['Content-Type'] === 'application/json'
                ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (is_array($data) ? http_build_query($data) : (string) $data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        
        foreach ($normalizedHeaders as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new FiberException('HTTP request failed: ' . $error);
        }
        
        $responseHeaders = $this->parseHeaders(substr($response, 0, $headerSize));
        $responseBody = substr($response, $headerSize);
        
        return new Response($httpCode, $responseHeaders, $responseBody);
    }

    protected function parseHeaders(string $headerText): array
    {
        $headers = [];
        $lines = explode("\r\n", trim($headerText));
        
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }
        
        return $headers;
    }

    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        
        foreach ($headers as $key => $value) {
            $normalizedKey = str_replace('_', '-', ucwords(strtolower((string) $key), '_-'));
            $normalized[$normalizedKey] = $value;
        }
        
        return $normalized;
    }

    protected function detectContentType(mixed $data): string
    {
        if (is_array($data) || $data instanceof \JsonSerializable || $data instanceof \stdClass) {
            return 'application/json';
        }
        
        return 'application/x-www-form-urlencoded';
    }

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

    public function getClient()
    {
        return $this->client;
    }
}
