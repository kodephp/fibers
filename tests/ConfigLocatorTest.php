<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Support\ConfigLocator;

class ConfigLocatorTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/fibers_cfg_' . uniqid();
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

    public function testDiscoverGenericConfig(): void
    {
        file_put_contents('fibers-config.php', "<?php return ['default_pool' => ['size' => 8]];");
        $normalized = ConfigLocator::loadNormalized();
        $this->assertSame(8, $normalized['default_pool']['size']);
    }

    public function testNormalizeThinkPHPKeys(): void
    {
        if (!is_dir('config')) mkdir('config');
        file_put_contents('config/fibers.php', "<?php return ['fiber_pool' => ['size' => 12, 'timeout' => 5]];");
        $normalized = ConfigLocator::loadNormalized();
        $this->assertArrayHasKey('default_pool', $normalized);
        $this->assertSame(12, $normalized['default_pool']['size']);
        $this->assertSame(5, $normalized['default_pool']['timeout']);
        $this->assertSame(5, $normalized['default_pool']['max_exec_time']);
    }
}


