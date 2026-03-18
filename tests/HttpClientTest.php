<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Kode\Fibers\HttpClient\HttpClient;
use Kode\Fibers\HttpClient\Response;
use Kode\Fibers\Exceptions\FiberException;

class HttpClientTest extends TestCase
{
    private HttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new HttpClient();
    }

    public function testMakeMethodCreatesInstance(): void
    {
        $instance = HttpClient::make(['timeout' => 10]);
        $this->assertInstanceOf(HttpClient::class, $instance);
    }

    public function testIsUsingNativeDriver(): void
    {
        $client = new HttpClient();
        $this->assertIsBool($client->isUsingNativeDriver());
    }

    public function testGetEnvironmentInfo(): void
    {
        $info = $this->httpClient->getEnvironmentInfo();
        
        $this->assertArrayHasKey('curl_available', $info);
        $this->assertArrayHasKey('has_http_client_package', $info);
        $this->assertArrayHasKey('has_psr7_package', $info);
        $this->assertArrayHasKey('using_native_driver', $info);
        $this->assertArrayHasKey('max_execution_time', $info);
        $this->assertArrayHasKey('memory_limit', $info);
    }

    public function testResponseClass(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], '{"success":true}');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['Content-Type' => 'application/json'], $response->getHeaders());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals('{"success":true}', $response->getBody());
        $this->assertEquals(['success' => true], $response->json());
        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isClientError());
        $this->assertFalse($response->isServerError());
    }

    public function testResponseIsClientError(): void
    {
        $response = new Response(404, [], 'Not Found');
        $this->assertTrue($response->isClientError());
        $this->assertFalse($response->isSuccessful());
    }

    public function testResponseIsServerError(): void
    {
        $response = new Response(500, [], 'Internal Server Error');
        $this->assertTrue($response->isServerError());
        $this->assertFalse($response->isSuccessful());
    }

    public function testNormalizeHeaders(): void
    {
        $reflection = new \ReflectionClass(HttpClient::class);
        $method = $reflection->getMethod('normalizeHeaders');
        $method->setAccessible(true);

        $headers = ['content_type' => 'application/json', 'x_custom_header' => 'value'];
        $normalizedHeaders = $method->invoke($this->httpClient, $headers);
        
        $this->assertEquals('application/json', $normalizedHeaders['Content-Type']);
        $this->assertEquals('value', $normalizedHeaders['X-Custom-Header']);
    }

    public function testDetectContentType(): void
    {
        $reflection = new \ReflectionClass(HttpClient::class);
        $method = $reflection->getMethod('detectContentType');
        $method->setAccessible(true);

        $this->assertEquals('application/json', $method->invoke($this->httpClient, ['key' => 'value']));
        $this->assertEquals('application/json', $method->invoke($this->httpClient, new \stdClass()));
        $this->assertEquals('application/x-www-form-urlencoded', $method->invoke($this->httpClient, 'key=value'));
    }

    public function testParseHeaders(): void
    {
        $reflection = new \ReflectionClass(HttpClient::class);
        $method = $reflection->getMethod('parseHeaders');
        $method->setAccessible(true);

        $headerText = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nX-Custom: value\r\n";
        $headers = $method->invoke($this->httpClient, $headerText);
        
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('value', $headers['X-Custom']);
    }
}
