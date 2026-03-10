<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Kode\Fibers\Commands\InitCommand;

class InitCommandTest extends TestCase
{
    private string $testDir;
    private string $configDir;
    
    protected function setUp(): void
    {
        $this->testDir = __DIR__ . '/tmp';
        $this->configDir = $this->testDir . '/config';
        
        // 创建测试目录
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
    }
    
    protected function tearDown(): void
    {
        // 清理测试文件
        if (is_dir($this->configDir)) {
            $files = glob($this->configDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->configDir);
        }
        
        if (is_dir($this->testDir)) {
            rmdir($this->testDir);
        }
    }
    
    public function testValidateFrameworkWithValidFramework(): void
    {
        $command = new InitCommand();
        
        // 使用反射调用私有方法
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('validateFramework');
        $method->setAccessible(true);
        
        // 捕获输出
        ob_start();
        $result = $method->invoke($command, 'LARAVEL');
        ob_end_clean();
        
        $this->assertEquals('laravel', $result);
    }
    
    public function testValidateFrameworkWithInvalidFramework(): void
    {
        $command = new InitCommand();
        
        // 使用反射调用私有方法
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('validateFramework');
        $method->setAccessible(true);
        
        // 捕获输出
        ob_start();
        $result = $method->invoke($command, 'invalid');
        ob_end_clean();
        
        $this->assertEquals('default', $result);
    }
    
    public function testGetProviderClass(): void
    {
        $command = new InitCommand();
        
        // 使用反射调用私有方法
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getProviderClass');
        $method->setAccessible(true);
        
        $this->assertEquals(
            'Kode\Fibers\Providers\LaravelServiceProvider',
            $method->invoke($command, 'laravel')
        );
        
        $this->assertEquals(
            'Kode\Fibers\Providers\SymfonyBundle',
            $method->invoke($command, 'symfony')
        );
        
        $this->assertEquals(
            'Kode\Fibers\Providers\GenericProvider',
            $method->invoke($command, 'default')
        );
    }
    
    public function testGenerateConfigContent(): void
    {
        $command = new InitCommand();
        
        // 使用反射调用私有方法
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('generateConfigContent');
        $method->setAccessible(true);
        
        $content = $method->invoke($command, 'default');
        
        // 检查是否包含关键配置项
        $this->assertStringContainsString('default_pool', $content);
        $this->assertStringContainsString('channels', $content);
        $this->assertStringContainsString('features', $content);
        $this->assertStringContainsString('framework', $content);
        $this->assertStringContainsString('environment', $content);
        
        // 检查是否包含正确的框架名称
        $this->assertStringContainsString("'name' => env('APP_FRAMEWORK', 'default')", $content);
        
        // 检查是否包含正确的提供者类
        $this->assertStringContainsString('GenericProvider', $content);
    }
    
    public function testSupportedFrameworksConstant(): void
    {
        $command = new InitCommand();
        
        // 使用反射获取常量
        $reflection = new \ReflectionClass($command);
        $constant = $reflection->getConstant('SUPPORTED_FRAMEWORKS');
        
        $this->assertArrayHasKey('laravel', $constant);
        $this->assertArrayHasKey('symfony', $constant);
        $this->assertArrayHasKey('yii3', $constant);
        $this->assertArrayHasKey('thinkphp', $constant);
        $this->assertArrayHasKey('default', $constant);
    }
}