<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests\Core;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Core\AutomaticTimeoutHandler;
use Nova\Fibers\Attributes\Timeout;

/**
 * 自动超时处理测试类
 */
class AutomaticTimeoutHandlerTest extends TestCase
{
    /**
     * 测试带超时控制的方法执行
     */
    public function testMethodWithTimeout(): void
    {
        $service = new class {
            #[Timeout(1)]
            public function fastMethod()
            {
                return "fast";
            }
            
            #[Timeout(1)]
            public function slowMethod()
            {
                sleep(2);
                return "slow";
            }
        };
        
        // 测试快速方法
        $result = AutomaticTimeoutHandler::applyTimeout($service, 'fastMethod');
        $this->assertEquals("fast", $result);
        
        // 测试慢速方法应该超时
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('execution timed out');
        AutomaticTimeoutHandler::applyTimeout($service, 'slowMethod');
    }
    
    /**
     * 测试没有超时属性的方法
     */
    public function testMethodWithoutTimeout(): void
    {
        $service = new class {
            public function normalMethod()
            {
                return "normal";
            }
        };
        
        $result = AutomaticTimeoutHandler::applyTimeout($service, 'normalMethod');
        $this->assertEquals("normal", $result);
    }
    
    /**
     * 测试不存在的方法
     */
    public function testNonExistentMethod(): void
    {
        $service = new class {
            public function existingMethod()
            {
                return "exists";
            }
        };
        
        $result = AutomaticTimeoutHandler::applyTimeout($service, 'existingMethod');
        $this->assertEquals("exists", $result);
        
        // 测试不存在的方法应该抛出异常
        $this->expectException(\Error::class);
        AutomaticTimeoutHandler::applyTimeout($service, 'nonExistentMethod');
    }
}