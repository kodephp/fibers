<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests\Pool;

use Kode\Fibers\Pool\ConnectionPool;
use PHPUnit\Framework\TestCase;

/**
 * 连接池 ID 生成：资源型连接必须一一对应，不得相撞。
 *
 * 回归背景：旧版对非对象连接走 md5(serialize($conn))，而 PHP 会把所有
 * 资源（流/socket）序列化成同一个标量——任意两个资源得到相同池 ID，
 * activeConnections 记账互相覆盖，release/take 配对错乱。
 */
final class ConnectionPoolIdTest extends TestCase
{
    private function idOf(mixed $connection): string
    {
        $pool = (new \ReflectionClass(ConnectionPool::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass(ConnectionPool::class))->getMethod('getConnectionId');

        return (string) $method->invoke($pool, $connection);
    }

    public function testTwoStreamResourcesGetDistinctIds(): void
    {
        $a = fopen('php://memory', 'r');
        $b = fopen('php://memory', 'r');
        self::assertIsResource($a);
        self::assertIsResource($b);

        try {
            $idA = $this->idOf($a);
            $idB = $this->idOf($b);

            self::assertNotSame($idA, $idB, '两个不同资源的池 ID 必须不同（serialize 会把它们压成同一个标量）');
            self::assertStringStartsWith('resource:', $idA);
            self::assertStringContainsString('stream', $idA);
        } finally {
            fclose($a);
            fclose($b);
        }
    }

    public function testSameResourceYieldsStableId(): void
    {
        $stream = fopen('php://memory', 'r');

        try {
            self::assertSame($this->idOf($stream), $this->idOf($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testObjectBranchUnchanged(): void
    {
        $obj = new \stdClass();

        self::assertSame(spl_object_id($obj) . ':' . \stdClass::class, $this->idOf($obj));
    }

    public function testScalarAndArrayBranchRemainsDeterministic(): void
    {
        self::assertSame(md5(serialize('conn')), $this->idOf('conn'));
        self::assertSame(md5(serialize(['host' => 'db', 'port' => 3306])), $this->idOf(['host' => 'db', 'port' => 3306]));
        self::assertNotSame($this->idOf(['a' => 1]), $this->idOf(['a' => 2]));
    }
}
