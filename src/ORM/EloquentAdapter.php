<?php

declare(strict_types=1);

namespace Kode\Fibers\ORM;

use Kode\Fibers\Fibers;

class EloquentAdapter implements ORMAdapterInterface
{
    public function __construct(protected object $connection)
    {
    }

    public function transaction(callable $callback): mixed
    {
        return Fibers::go(fn() => $this->connection->transaction($callback));
    }

    public function query(string $statement, array $bindings = []): mixed
    {
        return Fibers::go(fn() => $this->connection->select($statement, $bindings));
    }
}
