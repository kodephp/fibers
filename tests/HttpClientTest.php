<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Kode\Fibers\HttpClient\HttpClient;
use Kode\Fibers\Exceptions\FiberException;
use Kode\HttpClient\HttpClientInterface as BaseHttpClient;
use Kode\HttpClient\Context\Context as HttpContext;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Message\RequestInterface;

class HttpClientTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|BaseHttpClient
     */
    private $mockBaseClient;

    /**
     * @var HttpClient
     */
    private $httpClient;

    protected function setUp(): void
    {
        $this->mockBaseClient = $this->createMock(BaseHttpClient::class);
        $this->httpClient = new HttpClient([], $this->mockBaseClient);
    }

    public function testMakeMethodCreatesInstance()
    {
        $instance = HttpClient::make(['timeout' => 10]);
        $this->assertInstanceOf(HttpClient::class, $instance);
    }

    public function testGetMethod()
    {
        $mockResponse = new PsrResponse(200, ['Content-Type' => 'application/json']);
        $this->mockBaseClient->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->callback(function (RequestInterface $request) {
                    return $request->getMethod() === 'GET' &&
                        (string) $request->getUri() === 'https://example.com' &&
                        $request->getHeaderLine('Content-Type') === 'application/json';
                }),
                $this->isInstanceOf(HttpContext::class)
            )
            ->willReturn($mockResponse);

        $response = $this->httpClient->get('https://example.com', ['Content-Type' => 'application/json'], ['timeout' => 5]);
        $this->assertInstanceOf(PsrResponse::class, $response);
    }

    public function testPostMethod()
    {
        $mockResponse = new PsrResponse(201);
        $this->mockBaseClient->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->callback(function (RequestInterface $request) {
                    return $request->getMethod() === 'POST' &&
                        (string) $request->getUri() === 'https://example.com/api' &&
                        (string) $request->getBody() === '{"name":"test"}';
                }),
                null
            )
            ->willReturn($mockResponse);

        $response = $this->httpClient->post('https://example.com/api', ['name' => 'test']);
        $this->assertInstanceOf(PsrResponse::class, $response);
    }

    public function testPutMethod()
    {
        $mockResponse = new PsrResponse(200);
        $this->mockBaseClient->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->callback(function (RequestInterface $request) {
                    return $request->getMethod() === 'PUT';
                }),
                null
            )
            ->willReturn($mockResponse);
        $response = $this->httpClient->put('https://example.com/api/1', ['name' => 'updated']);
        $this->assertInstanceOf(PsrResponse::class, $response);
    }

    public function testDeleteMethod()
    {
        $mockResponse = new PsrResponse(204);
        $this->mockBaseClient->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->callback(function (RequestInterface $request) {
                    return $request->getMethod() === 'DELETE';
                }),
                null
            )
            ->willReturn($mockResponse);
        $response = $this->httpClient->delete('https://example.com/api/1');
        $this->assertInstanceOf(PsrResponse::class, $response);
    }

    public function testPatchMethod()
    {
        $mockResponse = new PsrResponse(200);
        $this->mockBaseClient->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->callback(function (RequestInterface $request) {
                    return $request->getMethod() === 'PATCH';
                }),
                null
            )
            ->willReturn($mockResponse);
        $response = $this->httpClient->patch('https://example.com/api/1', ['name' => 'patched']);
        $this->assertInstanceOf(PsrResponse::class, $response);
    }

    public function testHeadMethod()
    {
        $mockResponse = new PsrResponse(200);
        $this->mockBaseClient->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->callback(function (RequestInterface $request) {
                    return $request->getMethod() === 'HEAD';
                }),
                null
            )
            ->willReturn($mockResponse);
        $response = $this->httpClient->head('https://example.com');
        $this->assertInstanceOf(PsrResponse::class, $response);
    }

    public function testOptionsMethod()
    {
        $mockResponse = new PsrResponse(200);
        $this->mockBaseClient->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->callback(function (RequestInterface $request) {
                    return $request->getMethod() === 'OPTIONS';
                }),
                null
            )
            ->willReturn($mockResponse);
        $response = $this->httpClient->options('https://example.com');
        $this->assertInstanceOf(PsrResponse::class, $response);
    }

    public function testSendMethodWithException()
    {
        // 设置模拟行为抛出异常
        $this->mockBaseClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException(new \Exception('Connection error'));
        
        // 验证异常
        $this->expectException(FiberException::class);
        $this->expectExceptionMessage('HTTP request failed: Connection error');
        
        // 执行测试
        $this->httpClient->send('GET', 'https://example.com');
    }

    public function testGetEnvironmentInfo()
    {
        $info = $this->httpClient->getEnvironmentInfo();
        
        $this->assertArrayHasKey('curl_available', $info);
        $this->assertArrayHasKey('curl_version', $info);
        $this->assertArrayHasKey('max_execution_time', $info);
        $this->assertArrayHasKey('memory_limit', $info);
        $this->assertArrayHasKey('disabled_functions', $info);
    }

    public function testConcurrentRequests()
    {
        $requests = [
            ['method' => 'GET', 'url' => 'https://example.com/1'],
            ['method' => 'GET', 'url' => 'https://example.com/2'],
        ];

        $mockResponse = new PsrResponse(200);
        $this->mockBaseClient->expects($this->exactly(2))
            ->method('sendRequest')
            ->willReturn($mockResponse);

        $result = $this->httpClient->concurrent($requests, 2, 1, true);
        $this->assertSame(2, $result['success_count']);
        $this->assertSame(0, $result['error_count']);

        $reflection = new \ReflectionClass(HttpClient::class);
        $normalizeHeadersMethod = $reflection->getMethod('normalizeHeaders');
        $normalizeHeadersMethod->setAccessible(true);

        $headers = ['content-type' => 'application/json', 'x-custom-header' => 'value'];
        $normalizedHeaders = $normalizeHeadersMethod->invoke($this->httpClient, $headers);
        $this->assertEquals('application/json', $normalizedHeaders['Content-Type']);
        $this->assertEquals('value', $normalizedHeaders['X-Custom-Header']);

        $detectContentTypeMethod = $reflection->getMethod('detectContentType');
        $detectContentTypeMethod->setAccessible(true);
        $this->assertEquals('application/json', $detectContentTypeMethod->invoke($this->httpClient, ['key' => 'value']));
        $this->assertEquals('application/x-www-form-urlencoded', $detectContentTypeMethod->invoke($this->httpClient, 'key=value'));
    }

    public function testGetClient()
    {
        $client = $this->httpClient->getClient();
        $this->assertInstanceOf(BaseHttpClient::class, $client);
        $this->assertSame($this->mockBaseClient, $client);
    }
}
