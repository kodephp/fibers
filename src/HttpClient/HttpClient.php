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
 * 需要: composer require kode/http-client guzzlehttp/psr7
 */
class HttpClient
{
    protected $client;
    protected array $clientOptions = [];

    public function __construct(array $options = [], $client = null)
    {
        $this->ensureDependencies();
        $this->clientOptions = $options;
        $this->client = $client ?? new \Kode\HttpClient\HttpClient(new \Kode\HttpClient\Driver\CurlDriver());
    }

    protected function ensureDependencies(): void
    {
        if (!class_exists(\Kode\HttpClient\HttpClient::class)) {
            throw new FiberException(
                'HTTP client requires kode/http-client package. ' .
                'Install it with: composer require kode/http-client'
            );
        }
        
        if (!class_exists(\GuzzleHttp\Psr7\Request::class)) {
            throw new FiberException(
                'HTTP client requires guzzlehttp/psr7 package. ' .
                'Install it with: composer require guzzlehttp/psr7'
            );
        }
    }

    public static function make(array $options = []): static
    {
        return new static($options);
    }

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

    #[FiberSafe]
    public function get(string $url, array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('GET', $url, [], $headers, $options);
    }

    #[FiberSafe]
    public function post(string $url, mixed $data = [], array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('POST', $url, $data, $headers, $options);
    }

    #[FiberSafe]
    public function put(string $url, mixed $data = [], array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('PUT', $url, $data, $headers, $options);
    }

    #[FiberSafe]
    public function delete(string $url, array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('DELETE', $url, [], $headers, $options);
    }

    #[FiberSafe]
    public function patch(string $url, mixed $data = [], array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('PATCH', $url, $data, $headers, $options);
    }

    #[FiberSafe]
    public function head(string $url, array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('HEAD', $url, [], $headers, $options);
    }

    #[FiberSafe]
    public function options(string $url, array $headers = [], array $options = []): ResponseInterface
    {
        return $this->send('OPTIONS', $url, [], $headers, $options);
    }

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

            $request = new \GuzzleHttp\Psr7\Request($method, $url, $normalizedHeaders, $body);
            $this->applyContext($options);

            return $this->client->sendRequest($request);
        } catch (\Throwable $e) {
            throw new FiberException('HTTP request failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        
        foreach ($headers as $key => $value) {
            $normalizedKey = str_replace('_', '-', ucwords(strtolower($key), '_-'));
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

    protected function applyContext(array $options): void
    {
        if (isset($options['timeout']) && class_exists(\Kode\HttpClient\Context\Context::class)) {
            \Kode\HttpClient\Context\Context::setTimeout((float) $options['timeout']);
        }
    }

    public function getClient()
    {
        return $this->client;
    }
}
