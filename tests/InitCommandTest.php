<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Commands\InitCommand;

class InitCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/fibers_init_' . uniqid();
        mkdir($this->tmpDir);
        $this->chdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->chdir(__DIR__ . '/..');
        $this->rrmdir($this->tmpDir);
        parent::tearDown();
    }

    private function chdir(string $dir): void
    {
        chdir($dir);
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

    public function testGenerateGenericConfig(): void
    {
        $cmd = new InitCommand();
        $code = $cmd->handle(['options' => ['force' => true]]);
        $this->assertSame(0, $code);
        $this->assertFileExists(getcwd() . '/fibers-config.php');
        $config = require getcwd() . '/fibers-config.php';
        $this->assertIsArray($config);
        $this->assertArrayHasKey('default_pool', $config);
        $this->assertArrayHasKey('channels', $config);
    }

    public function testForceOverwrite(): void
    {
        file_put_contents('fibers-config.php', '<?php return ["foo" => "bar"];');
        $cmd = new InitCommand();
        $code = $cmd->handle(['options' => ['force' => true]]);
        $this->assertSame(0, $code);
        $config = require getcwd() . '/fibers-config.php';
        $this->assertArrayHasKey('default_pool', $config);
    }
}


