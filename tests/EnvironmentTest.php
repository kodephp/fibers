<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Support\Environment;
use Nova\Fibers\Support\CpuInfo;

/**
 * Environment 测试类
 *
 * @package Nova\Fibers\Tests
 */
class EnvironmentTest extends TestCase
{
    /**
     * 测试检查纤程支持
     *
     * @covers \Nova\Fibers\Support\Environment::checkFiberSupport
     * @return void
     */
    public function testCheckFiberSupport(): void
    {
        $supportsFibers = Environment::checkFiberSupport();

        // 在PHP 8.1+环境中应该支持纤程
        if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
            $this->assertTrue($supportsFibers);
        } else {
            $this->assertFalse($supportsFibers);
        }
    }

    /**
     * 测试诊断功能
     *
     * @covers \Nova\Fibers\Support\Environment::diagnose
     * @return void
     */
    public function testDiagnose(): void
    {
        $issues = Environment::diagnose();

        // 诊断结果应该是一个数组
        $this->assertIsArray($issues);
    }

    /**
     * 测试安全析构模式检查
     *
     * @covers \Nova\Fibers\Support\Environment::shouldEnableSafeDestructMode
     * @return void
     */
    public function testShouldEnableSafeDestructMode(): void
    {
        $shouldEnable = Environment::shouldEnableSafeDestructMode();

        // 在PHP 8.4之前应该启用安全析构模式
        if (version_compare(PHP_VERSION, '8.4.0', '<')) {
            $this->assertTrue($shouldEnable);
        } else {
            // 在PHP 8.4+中可能不需要启用
            $this->assertIsBool($shouldEnable);
        }
    }

    /**
     * 测试CPU信息获取
     *
     * @covers \Nova\Fibers\Support\CpuInfo::get
     * @return void
     */
    public function testCpuInfo(): void
    {
        $cpuCount = CpuInfo::get();

        // CPU核心数应该是一个正整数
        $this->assertIsInt($cpuCount);
        $this->assertGreaterThan(0, $cpuCount);
    }
}
