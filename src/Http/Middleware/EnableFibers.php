<?php

declare(strict_types=1);

namespace Kode\Fibers\Http\Middleware;

use Closure;
use Kode\Fibers\Fibers;
use Kode\Fibers\Context\Context;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 启用纤程支持的中间件
 * 
 * 此中间件为HTTP请求提供纤程支持，设置请求上下文，并确保请求完成后清理资源。
 * 支持标准PSO-15中间件接口，可以在各种框架中使用。
 */
class EnableFibers implements MiddlewareInterface
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param RequestHandlerInterface $handler
     * @return Response
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // 确保启用安全析构模式
        Fibers::enableSafeDestructMode();
        
        // 设置请求上下文
        $this->setRequestContext($request);
        
        try {
            // 处理请求
            $response = $handler->handle($request);
            
            return $response;
        } finally {
            // 清理上下文
            $this->cleanup();
        }
    }
    
    /**
     * 为Laravel等框架提供兼容的处理方法
     *
     * @param mixed $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // 确保启用安全析构模式
        Fibers::enableSafeDestructMode();
        
        // 设置请求上下文
        $this->setRequestContext($request);
        
        try {
            // 处理请求
            $response = $next($request);
            
            return $response;
        } finally {
            // 清理上下文
            $this->cleanup();
        }
    }
    
    /**
     * 设置请求上下文数据
     *
     * @param mixed $request
     */
    protected function setRequestContext($request): void
    {
        // 生成请求ID
        $requestId = uniqid('req_', true);
        
        // 基础上下文数据
        $context = [
            'request_id' => $requestId,
            'request_time' => microtime(true),
        ];
        
        // 根据请求类型设置不同的上下文数据
        if (method_exists($request, 'header')) {
            // PSR-7 或 Laravel 请求
            $context['user_agent'] = $request->header('User-Agent') ?? '';
            $context['ip'] = $request->header('X-Forwarded-For') ?? 
                           ($request->header('Client-Ip') ?? 'unknown');
        }
        
        // 设置上下文
        Context::setMultiple($context);
        Fibers::setAppContext($context);
    }
    
    /**
     * 清理资源
     */
    protected function cleanup(): void
    {
        // 清理上下文
        Context::clear();
        
        // 触发垃圾回收
        gc_collect_cycles();
    }
}