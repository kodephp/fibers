<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests\Context;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Context\EnhancedContextManager as ContextManager;
use Nova\Fibers\Context\Context;

/**
 * 增强上下文管理器测试类
 */
class EnhancedContextManagerTest extends TestCase
{
    protected function setUp(): void
    {
        // 清理上下文
        ContextManager::clear();
    }

    protected function tearDown(): void
    {
        // 清理上下文
        ContextManager::clear();
    }

    /**
     * 测试基本上下文功能
     */
    public function testBasicContext(): void
    {
        // 设置上下文值
        ContextManager::withValue('user_id', 123);
        ContextManager::withValue('session_id', 'abc');

        // 获取上下文值
        $this->assertEquals(123, ContextManager::getValue('user_id'));
        $this->assertEquals('abc', ContextManager::getValue('session_id'));
        $this->assertNull(ContextManager::getValue('nonexistent'));
    }

    /**
     * 测试上下文隔离功能
     */
    public function testContextIsolation(): void
    {
        // 设置初始上下文
        ContextManager::withValue('user_id', 123);
        ContextManager::withValue('session_id', 'abc');
        
        // 隔离某些键
        ContextManager::withIsolation(['user_id']);
        
        // 验证隔离效果
        $this->assertNull(ContextManager::getValue('user_id')); // 被隔离
        $this->assertEquals('abc', ContextManager::getValue('session_id')); // 未被隔离
    }

    /**
     * 测试上下文权限控制
     */
    public function testContextPermissions(): void
    {
        // 设置权限
        ContextManager::setPermission('sensitive_data', false);
        ContextManager::setPermission('public_data', true);
        
        // 允许设置公共数据
        ContextManager::withValue('public_data', 'public_value');
        $this->assertEquals('public_value', ContextManager::getValue('public_data'));
        
        // 不允许设置敏感数据
        $this->expectException(\RuntimeException::class);
        ContextManager::withValue('sensitive_data', 'sensitive_value', true);
    }

    /**
     * 测试上下文继承
     */
    public function testContextInheritance(): void
    {
        // 在父纤程中设置上下文
        ContextManager::withValue('user_id', 123);
        ContextManager::withValue('session_id', 'abc');
        
        // 模拟父纤程ID
        $parentFiberId = 'parent_fiber_id';
        
        // 获取当前上下文并保存到父纤程ID映射中
        $reflection = new \ReflectionClass(ContextManager::class);
        $currentContextIdProperty = $reflection->getProperty('currentContextId');
        $currentContextIdProperty->setAccessible(true);
        $currentContextId = $currentContextIdProperty->getValue();
        
        $contextsProperty = $reflection->getProperty('contexts');
        $contextsProperty->setAccessible(true);
        $contexts = $contextsProperty->getValue();
        
        // 手动设置父纤程的上下文映射
        $currentContextId[$parentFiberId] = array_key_first($currentContextId);
        $currentContextIdProperty->setValue($currentContextId);
        
        // 继承父纤程上下文
        ContextManager::inheritFromParent($parentFiberId);
        
        // 验证继承的值
        $this->assertEquals(123, ContextManager::getValue('user_id'));
        $this->assertEquals('abc', ContextManager::getValue('session_id'));
    }
}