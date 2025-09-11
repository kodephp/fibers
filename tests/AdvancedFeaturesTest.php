<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Context\ContextManager;
use Nova\Fibers\Support\Environment;

/**
 * 高级特性测试类
 *
 * @package Nova\Fibers\Tests
 */
class AdvancedFeaturesTest extends TestCase
{
    /**
     * 测试环境是否支持纤程
     *
     * @covers \Nova\Fibers\Support\Environment::checkFiberSupport
     * @return void
     */
    public function testEnvironmentSupportsFibers(): void
    {
        $this->assertTrue(Environment::checkFiberSupport());
    }

    /**
     * 测试上下文传递
     *
     * @covers \Nova\Fibers\Context\ContextManager
     * @return void
     */
    public function testContextPassing(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $testResult = null;
        
        $fiber = new \Fiber(function () use (&$testResult) {
            // 在纤程中初始化上下文
            $context = new \Nova\Fibers\Context\Context('test');
            $context = $context->withValue('user_id', 123);
            $context = $context->withValue('request_id', 'req_' . uniqid());
            ContextManager::setCurrentContext($context);
            
            // 验证初始上下文
            $userId = ContextManager::getValue('user_id');
            $requestId = ContextManager::getValue('request_id');
            
            // 验证获取不存在的键返回null
            $nonexistent = ContextManager::getValue('nonexistent');
            
            // 修改上下文
            $newContext = ContextManager::withValue('fiber_result', 'success');
            $newContext = ContextManager::withValue('fiber_executed', true);
            $newContext = ContextManager::withValue('processed_user_id', $userId);
            
            // 验证上下文更新（在同一个纤程中）
            $fiberResult = ContextManager::getValue('fiber_result');
            $fiberExecuted = ContextManager::getValue('fiber_executed');
            $processedUserId = ContextManager::getValue('processed_user_id');
            
            $testResult = [
                'user_id' => $userId,
                'request_id' => $requestId,
                'nonexistent' => $nonexistent,
                'fiber_result' => $fiberResult,
                'fiber_executed' => $fiberExecuted,
                'processed_user_id' => $processedUserId
            ];
        });

        $fiber->start();
        
        // 验证纤程执行结果
        $this->assertNotNull($testResult);
        $this->assertEquals(123, $testResult['user_id']);
        $this->assertStringStartsWith('req_', $testResult['request_id']);
        $this->assertNull($testResult['nonexistent']);
        $this->assertEquals('success', $testResult['fiber_result']);
        $this->assertTrue($testResult['fiber_executed']);
        $this->assertEquals(123, $testResult['processed_user_id']);
    }

    /**
     * 测试运行环境诊断
     *
     * @covers \Nova\Fibers\Support\Environment::diagnose
     * @return void
     */
    public function testEnvironmentDiagnostics(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $issues = Environment::diagnose();
        
        // 验证诊断结果是一个数组
        $this->assertIsArray($issues);
        
        // 验证PHP版本检查存在
        $foundPhpVersionCheck = false;
        foreach ($issues as $issue) {
            if (isset($issue['type']) && $issue['type'] === 'php_version') {
                $foundPhpVersionCheck = true;
                break;
            }
        }
        
        // 如果PHP版本低于8.1，应该找到php_version检查
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $this->assertTrue($foundPhpVersionCheck);
        }
    }
}
