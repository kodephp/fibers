<?php

declare(strict_types=1);

namespace Kode\Fibers\HttpClient;

use Kode\Fibers\Attributes\FiberSafe;
use Psr\Http\Message\ResponseInterface;

/**
 * Fiber-safe HTTP response wrapper
 *
 * This class provides fiber-aware methods for working with HTTP responses.
 */
class Response
{
    /**
     * 底层 PSR-7 响应对象
     */
    protected ResponseInterface $response;

    /**
     * Create a new fiber HTTP response instance
     *
     * @param ResponseInterface $response The underlying response
     */
    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * Create a new fiber HTTP response instance
     *
     * @param ResponseInterface $response The underlying response
     * @return static
     */
    public static function make(ResponseInterface $response): static
    {
        return new static($response);
    }

    /**
     * Get the HTTP status code
     *
     * @return int
     */
    #[FiberSafe]
    public function statusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * Get the HTTP status message
     *
     * @return string
     */
    #[FiberSafe]
    public function statusMessage(): string
    {
        return $this->response->getReasonPhrase();
    }

    /**
     * Get the response body
     *
     * @return string
     */
    #[FiberSafe]
    public function body(): string
    {
        return (string) $this->response->getBody();
    }

    /**
     * Get the response body as JSON
     *
     * @param bool $assoc Return associative array instead of object
     * @return mixed
     */
    #[FiberSafe]
    public function json(bool $assoc = true): mixed
    {
        return json_decode($this->body(), $assoc);
    }

    /**
     * Get all response headers
     *
     * @return array
     */
    #[FiberSafe]
    public function headers(): array
    {
        return $this->response->getHeaders();
    }

    /**
     * Get a specific response header
     *
     * @param string $name The header name
     * @param mixed $default Default value if header not found
     * @return mixed
     */
    #[FiberSafe]
    public function header(string $name, mixed $default = null): mixed
    {
        $headers = $this->headers();
        $name = strtolower($name);
        
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $name) {
                return is_array($value) && count($value) === 1 ? $value[0] : $value;
            }
        }
        
        return $default;
    }

    /**
     * Check if the response is successful (status code 2xx)
     *
     * @return bool
     */
    #[FiberSafe]
    public function isSuccessful(): bool
    {
        $code = $this->statusCode();
        return $code >= 200 && $code < 300;
    }

    /**
     * Check if the response is a redirect (status code 3xx)
     *
     * @return bool
     */
    #[FiberSafe]
    public function isRedirect(): bool
    {
        $code = $this->statusCode();
        return $code >= 300 && $code < 400;
    }

    /**
     * Check if the response indicates an error (status code 4xx or 5xx)
     *
     * @return bool
     */
    #[FiberSafe]
    public function isError(): bool
    {
        $code = $this->statusCode();
        return $code >= 400;
    }

    /**
     * Get the content type of the response
     *
     * @return string
     */
    #[FiberSafe]
    public function contentType(): string
    {
        $contentType = $this->header('content-type', '');
        if (strpos($contentType, ';') !== false) {
            return trim(explode(';', $contentType)[0]);
        }
        return $contentType;
    }

    /**
     * Get the underlying HTTP response
     *
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
