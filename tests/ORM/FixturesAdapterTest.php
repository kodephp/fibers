<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests\ORM;

use Kode\Fibers\ORM\FixturesAdapter;
use PHPUnit\Framework\TestCase;

class FixturesAdapterTest extends TestCase
{
    public function testQuery(): void
    {
        $adapter = new FixturesAdapter([
            'users' => [['id' => 1, 'name' => 'u1']],
        ]);

        $rows = $adapter->query('users');
        $this->assertCount(1, $rows);
        $this->assertSame('u1', $rows[0]['name']);
    }

    public function testTransaction(): void
    {
        $adapter = new FixturesAdapter([
            'users' => [['id' => 1, 'name' => 'u1']],
        ]);

        $count = $adapter->transaction(static fn(array $fixtures) => count($fixtures['users']));
        $this->assertSame(1, $count);
    }
}
