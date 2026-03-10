<?php

declare(strict_types=1);

namespace Kode\Fibers\Contracts;

interface CircuitBreakerInterface
{
    public function allowRequest(): bool;

    public function recordSuccess(): void;

    public function recordFailure(): void;

    public function state(): string;

    public function metrics(): array;
}
