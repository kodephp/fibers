<?php

declare(strict_types=1);

namespace Kode\Fibers\Transaction;

/**
 * 持久化事务管理器
 */
class PersistentTransactionManager extends DistributedTransactionManager
{
    protected TransactionStorageInterface $storage;
    protected bool $persistEnabled = true;

    public function __construct(
        string $mode = self::MODE_2PC,
        int $timeout = 30,
        ?TransactionStorageInterface $storage = null
    ) {
        parent::__construct($mode, $timeout);

        $this->storage = $storage ?? new FileTransactionStorage();
    }

    /**
     * 设置存储
     */
    public function setStorage(TransactionStorageInterface $storage): self
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * 启用/禁用持久化
     */
    public function setPersistEnabled(bool $enabled): self
    {
        $this->persistEnabled = $enabled;
        return $this;
    }

    /**
     * 恢复未完成的事务
     */
    public function recover(): array
    {
        $recovered = [];

        $pending = $this->storage->listPending();
        foreach ($pending as $record) {
            if ($this->recoverTransaction($record)) {
                $recovered[] = $record->id;
            }
        }

        return $recovered;
    }

    /**
     * 恢复单个事务
     */
    protected function recoverTransaction(TransactionRecord $record): bool
    {
        $this->transactionId = $record->id;
        $this->participants = $record->participants;

        switch ($record->status) {
            case TransactionRecord::STATUS_PENDING:
                return $this->recoverPending($record);
            case TransactionRecord::STATUS_PREPARED:
                return $this->recoverPrepared($record);
            case TransactionRecord::STATUS_COMMITTING:
                return $this->recoverCommitting($record);
            case TransactionRecord::STATUS_ROLLING_BACK:
                return $this->recoverRollingBack($record);
            default:
                return false;
        }
    }

    protected function recoverPending(TransactionRecord $record): bool
    {
        try {
            foreach ($this->participants as $resourceId => &$participant) {
                if (($participant['status'] ?? '') === 'pending') {
                    $result = ($participant['prepare'])();
                    $participant['status'] = 'prepared';
                    $this->log('recover_prepare_ok', ['resource_id' => $resourceId]);
                }
            }
            $this->commit();
            return true;
        } catch (\Throwable $e) {
            $this->rollback();
            return false;
        }
    }

    protected function recoverPrepared(TransactionRecord $record): bool
    {
        try {
            $this->commit();
            return true;
        } catch (\Throwable $e) {
            $this->rollback();
            return false;
        }
    }

    protected function recoverCommitting(TransactionRecord $record): bool
    {
        try {
            foreach ($this->participants as $resourceId => &$participant) {
                if (($participant['status'] ?? '') === 'prepared') {
                    ($participant['commit'])();
                    $participant['status'] = 'committed';
                }
            }
            $this->committed = true;
            return true;
        } catch (\Throwable $e) {
            $this->rollback();
            return false;
        }
    }

    protected function recoverRollingBack(TransactionRecord $record): bool
    {
        try {
            foreach ($this->participants as $resourceId => &$participant) {
                if (($participant['status'] ?? '') !== 'committed') {
                    ($participant['rollback'])();
                    $participant['status'] = 'rolled_back';
                }
            }
            $this->rolledBack = true;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 保存事务状态
     */
    protected function saveState(): void
    {
        if (!$this->persistEnabled) {
            return;
        }

        $record = new TransactionRecord($this->transactionId, $this->mode);
        $record->status = $this->getStatusRecord();
        $record->participants = $this->participants;
        $record->log = $this->transactionLog;
        $record->completedAt = $this->committed || $this->rolledBack ? microtime(true) : null;
        $record->retryCount = 0;

        $this->storage->save($record);
    }

    /**
     * 获取状态记录
     */
    protected function getStatusRecord(): string
    {
        if ($this->committed) {
            return TransactionRecord::STATUS_COMMITTED;
        }
        if ($this->rolledBack) {
            return TransactionRecord::STATUS_ROLLED_BACK;
        }

        $allPrepared = true;
        foreach ($this->participants as $p) {
            if (($p['status'] ?? '') !== 'prepared') {
                $allPrepared = false;
                break;
            }
        }

        if ($allPrepared) {
            return TransactionRecord::STATUS_PREPARED;
        }

        return TransactionRecord::STATUS_PENDING;
    }

    /**
     * 开始事务
     */
    #[\Override]
    public function begin(): void
    {
        parent::begin();
        $this->saveState();
    }

    /**
     * 提交事务
     */
    #[\Override]
    public function commit(): void
    {
        $this->updateStatus(TransactionRecord::STATUS_COMMITTING);
        parent::commit();
        $this->updateStatus(TransactionRecord::STATUS_COMMITTED);
    }

    /**
     * 回滚事务
     */
    #[\Override]
    public function rollback(): void
    {
        $this->updateStatus(TransactionRecord::STATUS_ROLLING_BACK);
        parent::rollback();
        $this->updateStatus(TransactionRecord::STATUS_ROLLED_BACK);
    }

    /**
     * 更新状态
     */
    protected function updateStatus(string $status): void
    {
        $this->storage->update($this->transactionId, [
            'status' => $status,
            'participants' => $this->participants,
            'log' => $this->transactionLog,
            'updated_at' => microtime(true),
        ]);
    }

    /**
     * 清理已完成的事务
     */
    public function cleanup(int $olderThanDays = 7): int
    {
        $count = 0;
        $cutoff = microtime(true) - ($olderThanDays * 86400);

        $statuses = [
            TransactionRecord::STATUS_COMMITTED,
            TransactionRecord::STATUS_ROLLED_BACK,
        ];

        foreach ($statuses as $status) {
            $records = $this->storage->listByStatus($status, 1000);
            foreach ($records as $record) {
                if ($record->completedAt && $record->completedAt < $cutoff) {
                    $this->storage->delete($record->id);
                    $count++;
                }
            }
        }

        return $count;
    }
}
