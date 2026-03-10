<?php

declare(strict_types=1);

namespace Kode\Fibers\ORM;

use Kode\Fibers\Fibers;

class FixturesAdapter implements ORMAdapterInterface
{
    public function __construct(protected array $fixtures = [])
    {
    }

    public function transaction(callable $callback): mixed
    {
        return Fibers::go(fn() => $callback($this->fixtures));
    }

    public function query(string $statement, array $bindings = []): mixed
    {
        return Fibers::go(fn() => $this->fixtures[$statement] ?? []);
    }
}
