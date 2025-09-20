<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Commands\InitCommand;

class FrameworkInitCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/fibers_framework_init_' . uniqid();
        mkdir($this->tmpDir);
        chdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpDir);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object === '.' || $object === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $object;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testDetectLaravelAndCreateConfig(): void
    {
        // 伪造 Laravel 环境
        if (!class_exists('Illuminate\\Foundation\\Application')) {
            eval('namespace Illuminate\\Foundation; class Application {}');
        }

        $cmd = new InitCommand();
        $code = $cmd->handle(['options' => ['force' => true]]);
        $this->assertSame(0, $code);
        $this->assertFileExists(getcwd() . '/config/fibers.php');
        $cfg = require getcwd() . '/config/fibers.php';
        $this->assertArrayHasKey('default_pool', $cfg);
    }

    public function testDetectYiiAndCreateConfig(): void
    {
        // 伪造 Yii 环境
        if (!class_exists('yii\\base\\Application')) {
            eval('namespace yii\\base; class Application {}');
        }

        $cmd = new InitCommand();
        $code = $cmd->handle(['options' => ['force' => true]]);
        $this->assertSame(0, $code);
        $this->assertFileExists(getcwd() . '/config/fibers.php');
        $cfg = require getcwd() . '/config/fibers.php';
        $this->assertArrayHasKey('default_pool', $cfg);
    }

    public function testDetectThinkPHPAndCreateConfig(): void
    {
        // 伪造 ThinkPHP 环境
        if (!defined('THINK_VERSION')) {
            define('THINK_VERSION', '8.0.0');
        }

        $cmd = new InitCommand();
        $code = $cmd->handle(['options' => ['force' => true]]);
        $this->assertSame(0, $code);
        $this->assertFileExists(getcwd() . '/config/fibers.php');
        $cfg = require getcwd() . '/config/fibers.php';
        $this->assertArrayHasKey('default_pool', $cfg);
    }
}


