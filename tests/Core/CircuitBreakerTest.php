<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests\Core;

use Kode\Fibers\Core\CircuitBreaker;
use PHPUnit\Framework\TestCase;

class CircuitBreakerTest extends TestCase
{
    public function testOpenAndRecoverFlow(): void
    {
        $breaker = new CircuitBreaker(2, 0.01, 1);
        $this->assertTrue($breaker->allowRequest());

        $breaker->recordFailure();
        $breaker->recordFailure();

        $this->assertSame(CircuitBreaker::STATE_OPEN, $breaker->state());
        $this->assertFalse($breaker->allowRequest());

        usleep(20000);
        $this->assertTrue($breaker->allowRequest());
        $this->assertSame(CircuitBreaker::STATE_HALF_OPEN, $breaker->state());

        $breaker->recordSuccess();
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $breaker->state());
    }
}
