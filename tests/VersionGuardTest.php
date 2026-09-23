<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests;

use Kode\Fibers\Fibers;
use PHPUnit\Framework\TestCase;

/**
 * 版本常量防漂移：本包 composer.json 带显式 `version` 字段，composer 只认它，
 * 类里的 VERSION 是给它做交叉核对的——发版时漏改一处，这里就拦下来。
 */
final class VersionGuardTest extends TestCase
{
    public function testVersionConstantMatchesComposerManifest(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(__DIR__ . '/../composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            $manifest['version'],
            Fibers::VERSION,
            'src/Fibers.php 的 VERSION 与 composer.json 的 version 不一致，发版时漏改了一处'
        );
        self::assertSame(Fibers::VERSION, Fibers::version(), 'version() 必须回读同一常量');
    }
}
