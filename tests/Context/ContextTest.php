<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests\Context;

use Kode\Context\Context;
use PHPUnit\Framework\TestCase;

class ContextTest extends TestCase
{
    public function testSnapshotAndRestore(): void
    {
        Context::clear();
        Context::set('k', 'v1');
        $snapshot = Context::copy();
        Context::set('k', 'v2');
        Context::restore($snapshot);

        $this->assertSame('v1', Context::get('k'));
    }

    public function testRunWith(): void
    {
        Context::clear();
        Context::set('trace_id', 'root');
        $result = Context::fork(function () {
            Context::set('trace_id', 'child');
            return Context::get('trace_id');
        });

        $this->assertSame('child', $result);
        $this->assertSame('root', Context::get('trace_id'));
    }
}
