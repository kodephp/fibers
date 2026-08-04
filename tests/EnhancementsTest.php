<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Kode\Fibers\Task\TaskQueue;
use Kode\Fibers\Concurrency\Runtime;
use Kode\Fibers\Support\CpuInfo;
use Kode\Fibers\Async\AsyncIO;
use Kode\Fibers\Transaction\PersistentTransactionManager;
use Kode\Fibers\Transaction\FileTransactionStorage;
use Kode\Fibers\Transaction\DistributedTransactionManager;

/**
 * 覆盖 v4 增强与缺陷修复：
 * - 事务文件 PSR-4 拆分（2PC / TCC / Saga）
 * - TaskQueue 真并发（不再串行）
 * - CpuInfo::clearCache 修复
 * - Diagnostics 最低版本与 composer 一致
 * - AsyncIO::parallel 真并发
 */
class EnhancementsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/fibers_enh_' . uniqid('', true);
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*.json') ?: []);
        @rmdir($this->tmpDir);
    }

    public function testTransactionManagerAutoloadsAfterPsr4Split(): void
    {
        $txn = new PersistentTransactionManager(
            DistributedTransactionManager::MODE_2PC,
            30,
            new FileTransactionStorage($this->tmpDir)
        );
        $this->assertInstanceOf(DistributedTransactionManager::class, $txn);
    }

    public function testTwoPhaseCommitCommitsAllParticipants(): void
    {
        $orderOk = false;
        $stockOk = false;
        $txn = new PersistentTransactionManager(
            DistributedTransactionManager::MODE_2PC,
            30,
            new FileTransactionStorage($this->tmpDir)
        );
        $txn->register('order',
            static fn() => true,
            static function () use (&$orderOk) { $orderOk = true; },
            static fn() => null
        );
        $txn->register('stock',
            static fn() => true,
            static function () use (&$stockOk) { $stockOk = true; },
            static fn() => null
        );

        $result = $txn->execute(static fn() => 'done');

        $this->assertSame('done', $result);
        $this->assertTrue($orderOk, 'order 应在 commit 阶段被提交');
        $this->assertTrue($stockOk, 'stock 应在 commit 阶段被提交');
    }

    public function testSagaModeRunsStepsInOrder(): void
    {
        $steps = [];
        $saga = new PersistentTransactionManager(
            DistributedTransactionManager::MODE_SAGA,
            30,
            new FileTransactionStorage($this->tmpDir)
        );
        $saga->addSagaStep('a', static function () use (&$steps) { $steps[] = 'a'; return 'A'; });
        $saga->addSagaStep('b', static function () use (&$steps) { $steps[] = 'b'; return 'B'; });

        $result = $saga->execute();

        $this->assertSame('B', $result);
        $this->assertSame(['a', 'b'], $steps);
    }

    public function testTaskQueueRunsConcurrentlyNotSerially(): void
    {
        $completed = [];
        $queue = new TaskQueue(['concurrency' => 2, 'auto_start' => false]);
        for ($i = 0; $i < 4; $i++) {
            $queue->add(static function () use ($i, &$completed): void {
                Runtime::sleep(0.1);
                $completed[$i] = true;
            });
        }

        $start = microtime(true);
        $queue->start();
        $elapsed = microtime(true) - $start;

        $this->assertCount(4, $completed, '4 个任务都应完成');
        // 并发上限 2，4×0.1s 应分两批约 0.2s；若为串行则接近 0.4s
        $this->assertLessThan(0.35, $elapsed, '并发应显著少于串行耗时');
        $this->assertSame(0, $queue->count(), '队列应已清空');
        $this->assertSame(0, $queue->runningCount());
    }

    public function testCpuInfoClearCache(): void
    {
        $first = CpuInfo::get();
        CpuInfo::clearCache();
        $second = CpuInfo::get();

        $this->assertGreaterThanOrEqual(1, $first);
        $this->assertGreaterThanOrEqual(1, $second);
    }

    public function testDiagnosticsMinPhpVersionMatchesComposer(): void
    {
        $ref = new \ReflectionClass(\Kode\Fibers\Support\Diagnostics::class);
        $this->assertSame('8.3.0', $ref->getConstant('MIN_PHP_VERSION'));
    }

    public function testAsyncIoParallelReturnsResults(): void
    {
        $io = new AsyncIO('stream');
        $results = null;
        $io->parallel([
            static function () { Runtime::sleep(0.1); return 'x'; },
            static function () { Runtime::sleep(0.1); return 'y'; },
        ], static function ($r) use (&$results): void { $results = $r; });

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }
}
