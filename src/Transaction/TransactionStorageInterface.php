<?php

declare(strict_types=1);

namespace Kode\Fibers\Transaction;

/**
 * 事务持久化接口
 */
interface TransactionStorageInterface
{
    public function save(TransactionRecord $record): bool;
    public function load(string $transactionId): ?TransactionRecord;
    public function update(string $transactionId, array $data): bool;
    public function delete(string $transactionId): bool;
    public function listPending(int $limit = 100): array;
    public function listByStatus(string $status, int $limit = 100): array;
}
