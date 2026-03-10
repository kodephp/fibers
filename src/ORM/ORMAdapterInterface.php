<?php

declare(strict_types=1);

namespace Kode\Fibers\ORM;

interface ORMAdapterInterface
{
    public function transaction(callable $callback): mixed;

    public function query(string $statement, array $bindings = []): mixed;
}
