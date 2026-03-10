<?php

declare(strict_types=1);

namespace Kode\Fibers\HttpClient;

use GuzzleHttp\Psr7\Request as Psr7Request;
use Kode\Fibers\Attributes\FiberSafe;
use Psr\Http\Message\RequestInterface;

/**
 * Fiber-safe HTTP request builder
 *
 * This class provides a fluent interface for building HTTP requests
 * that work seamlessly with fibers.
 */
class Request
{
    /**
     * 底层 PSR-7 请求对象
     */
    protected RequestInterface $request;

    /**
     * Create a new fiber HTTP request instance
     *
     * @param string $method The HTTP method
     * @param string $url The URL
     * @param mixed $data The request data
     * @param array $headers The request headers
     */
    public function __construct(string $method, string $url, mixed $data = [], array $headers = [])
    {
        $body = null;
        if ($data !== [] && $data !== null) {
            $body = is_array($data)
                ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string) $data;
        }
        $this->request = new Psr7Request($method, $url, $headers, $body);
    }

    /**
     * Create a new fiber HTTP request instance
     *
     * @param string $method The HTTP method
     * @param string $url The URL
     * @param mixed $data The request data
     * @param array $headers The request headers
     * @return static
     */
    public static function make(string $method, string $url, mixed $data = [], array $headers = []): static
    {
        return new static($method, $url, $data, $headers);
    }

    /**
     * Create a new GET request
     *
     * @param string $url The URL
     * @param array $headers The request headers
     * @return static
     */
    public static function get(string $url, array $headers = []): static
    {
        return new static('GET', $url, [], $headers);
    }

    /**
     * Create a new POST request
     *
     * @param string $url The URL
     * @param mixed $data The request data
     * @param array $headers The request headers
     * @return static
     */
    public static function post(string $url, mixed $data = [], array $headers = []): static
    {
        return new static('POST', $url, $data, $headers);
    }

    /**
     * Create a new PUT request
     *
     * @param string $url The URL
     * @param mixed $data The request data
     * @param array $headers The request headers
     * @return static
     */
    public static function put(string $url, mixed $data = [], array $headers = []): static
    {
        return new static('PUT', $url, $data, $headers);
    }

    /**
     * Create a new DELETE request
     *
     * @param string $url The URL
     * @param array $headers The request headers
     * @return static
     */
    public static function delete(string $url, array $headers = []): static
    {
        return new static('DELETE', $url, [], $headers);
    }

    /**
     * Add a header to the request
     *
     * @param string $name The header name
     * @param string $value The header value
     * @return $this
     */
    #[FiberSafe]
    public function header(string $name, string $value): static
    {
        $this->request = $this->request->withHeader($name, $value);
        return $this;
    }

    /**
     * Add multiple headers to the request
     *
     * @param array $headers The headers to add
     * @return $this
     */
    #[FiberSafe]
    public function headers(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
        return $this;
    }

    /**
     * Set the request data
     *
     * @param mixed $data The data to set
     * @return $this
     */
    #[FiberSafe]
    public function data(mixed $data): static
    {
        $body = is_array($data)
            ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $data;
        $this->request = $this->request->withBody(\GuzzleHttp\Psr7\Utils::streamFor($body));
        return $this;
    }

    /**
     * Set the request timeout
     *
     * @param float $timeout The timeout in seconds
     * @return $this
     */
    #[FiberSafe]
    public function timeout(float $timeout): static
    {
        return $this;
    }

    /**
     * 兼容旧 API，不在请求对象内存储传输选项
     */
    #[FiberSafe]
    public function setOption(string $name, mixed $value): static
    {
        return $this;
    }

    /**
     * Set a request option
     *
     * @param string $name The option name
     * @param mixed $value The option value
     * @return $this
     */
    #[FiberSafe]
    public function option(string $name, mixed $value): static
    {
        return $this->setOption($name, $value);
    }

    /**
     * Set multiple request options
     *
     * @param array $options The options to set
     * @return $this
     */
    #[FiberSafe]
    public function options(array $options): static
    {
        foreach ($options as $name => $value) {
            $this->option($name, $value);
        }
        return $this;
    }

    /**
     * Set the request body
     *
     * @param string $body The request body
     * @return $this
     */
    #[FiberSafe]
    public function body(string $body): static
    {
        $this->request = $this->request->withBody(\GuzzleHttp\Psr7\Utils::streamFor($body));
        return $this;
    }

    /**
     * Set the request content type
     *
     * @param string $contentType The content type
     * @return $this
     */
    #[FiberSafe]
    public function contentType(string $contentType): static
    {
        return $this->header('Content-Type', $contentType);
    }

    /**
     * Get the HTTP method
     *
     * @return string
     */
    #[FiberSafe]
    public function method(): string
    {
        return strtoupper($this->request->getMethod());
    }

    /**
     * Get the URL
     *
     * @return string
     */
    #[FiberSafe]
    public function url(): string
    {
        return (string) $this->request->getUri();
    }

    /**
     * Get the request data
     *
     * @return mixed
     */
    #[FiberSafe]
    public function getData(): mixed
    {
        return (string) $this->request->getBody();
    }

    /**
     * Get all request headers
     *
     * @return array
     */
    #[FiberSafe]
    public function getHeaders(): array
    {
        return $this->request->getHeaders();
    }

    /**
     * Get a specific request header
     *
     * @param string $name The header name
     * @param mixed $default Default value if header not found
     * @return mixed
     */
    #[FiberSafe]
    public function getHeader(string $name, mixed $default = null): mixed
    {
        $headers = $this->getHeaders();
        $name = strtolower($name);
        
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $name) {
                return is_array($value) && count($value) === 1 ? $value[0] : $value;
            }
        }
        
        return $default;
    }

    /**
     * Get the underlying HTTP request
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
