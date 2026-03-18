<?php

declare(strict_types=1);

namespace Kode\Fibers\HttpClient;

/**
 * HTTP 响应类
 *
 * 统一的 HTTP 响应封装，兼容 PSR-7 和原生实现
 */
class Response
{
    protected int $statusCode;
    protected array $headers;
    protected string $body;

    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function json(): mixed
    {
        return json_decode($this->body, true);
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    public function __toString(): string
    {
        return $this->body;
    }
}
