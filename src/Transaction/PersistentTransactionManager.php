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

/**
 * 事务记录
 */
class TransactionRecord
{
    public string $id;
    public string $mode;
    public string $status;
    public array $participants;
    public array $log;
    public float $createdAt;
    public ?float $updatedAt;
    public ?float $completedAt;
    public int $retryCount;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PREPARED = 'prepared';
    public const STATUS_COMMITTING = 'committing';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_ROLLING_BACK = 'rolling_back';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_FAILED = 'failed';

    public function __construct(string $id, string $mode)
    {
        $this->id = $id;
        $this->mode = $mode;
        $this->status = self::STATUS_PENDING;
        $this->participants = [];
        $this->log = [];
        $this->createdAt = microtime(true);
        $this->updatedAt = null;
        $this->completedAt = null;
        $this->retryCount = 0;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode,
            'status' => $this->status,
            'participants' => $this->participants,
            'log' => $this->log,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'completed_at' => $this->completedAt,
            'retry_count' => $this->retryCount,
        ];
    }

    public static function fromArray(array $data): self
    {
        $record = new self($data['id'], $data['mode']);
        $record->status = $data['status'];
        $record->participants = $data['participants'];
        $record->log = $data['log'];
        $record->createdAt = $data['created_at'];
        $record->updatedAt = $data['updated_at'];
        $record->completedAt = $data['completed_at'];
        $record->retryCount = $data['retry_count'];
        return $record;
    }
}

/**
 * 文件存储
 */
class FileTransactionStorage implements TransactionStorageInterface
{
    protected string $path;
    protected string $separator = PHP_EOL;

    public function __construct(string $path = '/tmp/transactions')
    {
        $this->path = rtrim($path, '/');
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function save(TransactionRecord $record): bool
    {
        $file = $this->getFilePath($record->id);
        $data = json_encode($record->toArray(), JSON_UNESCAPED_UNICODE);
        return file_put_contents($file, $data) !== false;
    }

    public function load(string $transactionId): ?TransactionRecord
    {
        $file = $this->getFilePath($transactionId);
        if (!file_exists($file)) {
            return null;
        }
        $data = json_decode(file_get_contents($file), true);
        return $data ? TransactionRecord::fromArray($data) : null;
    }

    public function update(string $transactionId, array $data): bool
    {
        $record = $this->load($transactionId);
        if (!$record) {
            return false;
        }

        foreach ($data as $key => $value) {
            if (property_exists($record, $key)) {
                $record->$key = $value;
            }
        }
        $record->updatedAt = microtime(true);

        return $this->save($record);
    }

    public function delete(string $transactionId): bool
    {
        $file = $this->getFilePath($transactionId);
        if (!file_exists($file)) {
            return true;
        }
        return unlink($file);
    }

    public function listPending(int $limit = 100): array
    {
        return $this->listByStatus(TransactionRecord::STATUS_PENDING, $limit);
    }

    public function listByStatus(string $status, int $limit = 100): array
    {
        $records = [];
        $files = glob($this->path . '/*.json');
        $count = 0;

        foreach ($files as $file) {
            if ($count >= $limit) {
                break;
            }

            $data = json_decode(file_get_contents($file), true);
            if ($data && ($data['status'] ?? '') === $status) {
                $records[] = TransactionRecord::fromArray($data);
                $count++;
            }
        }

        return $records;
    }

    protected function getFilePath(string $id): string
    {
        return $this->path . '/' . $id . '.json';
    }
}

/**
 * 数据库存储
 */
class DatabaseTransactionStorage implements TransactionStorageInterface
{
    protected \PDO $pdo;
    protected string $table;

    public function __construct(\PDO $pdo, string $table = 'transactions')
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->initTable();
    }

    protected function initTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id VARCHAR(64) PRIMARY KEY,
            mode VARCHAR(16) NOT NULL,
            status VARCHAR(32) NOT NULL,
            participants TEXT,
            log TEXT,
            created_at DOUBLE NOT NULL,
            updated_at DOUBLE,
            completed_at DOUBLE,
            retry_count INT DEFAULT 0,
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        )";
        $this->pdo->exec($sql);
    }

    public function save(TransactionRecord $record): bool
    {
        $sql = "INSERT INTO {$this->table} 
                (id, mode, status, participants, log, created_at, updated_at, completed_at, retry_count)
                VALUES (:id, :mode, :status, :participants, :log, :created_at, :updated_at, :completed_at, :retry_count)
                ON CONFLICT(id) DO UPDATE SET
                status = :status,
                participants = :participants,
                log = :log,
                updated_at = :updated_at,
                completed_at = :completed_at,
                retry_count = :retry_count";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $record->id,
            ':mode' => $record->mode,
            ':status' => $record->status,
            ':participants' => json_encode($record->participants),
            ':log' => json_encode($record->log),
            ':created_at' => $record->createdAt,
            ':updated_at' => $record->updatedAt,
            ':completed_at' => $record->completedAt,
            ':retry_count' => $record->retryCount,
        ]);
    }

    public function load(string $transactionId): ?TransactionRecord
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $transactionId]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return TransactionRecord::fromArray([
            'id' => $data['id'],
            'mode' => $data['mode'],
            'status' => $data['status'],
            'participants' => json_decode($data['participants'], true) ?? [],
            'log' => json_decode($data['log'], true) ?? [],
            'created_at' => (float) $data['created_at'],
            'updated_at' => $data['updated_at'] ? (float) $data['updated_at'] : null,
            'completed_at' => $data['completed_at'] ? (float) $data['completed_at'] : null,
            'retry_count' => (int) $data['retry_count'],
        ]);
    }

    public function update(string $transactionId, array $data): bool
    {
        $record = $this->load($transactionId);
        if (!$record) {
            return false;
        }

        foreach ($data as $key => $value) {
            if (property_exists($record, $key)) {
                $record->$key = $value;
            }
        }
        $record->updatedAt = microtime(true);

        return $this->save($record);
    }

    public function delete(string $transactionId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $transactionId]);
    }

    public function listPending(int $limit = 100): array
    {
        return $this->listByStatus(TransactionRecord::STATUS_PENDING, $limit);
    }

    public function listByStatus(string $status, int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = :status ORDER BY created_at ASC LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $records = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $records[] = TransactionRecord::fromArray([
                'id' => $data['id'],
                'mode' => $data['mode'],
                'status' => $data['status'],
                'participants' => json_decode($data['participants'], true) ?? [],
                'log' => json_decode($data['log'], true) ?? [],
                'created_at' => (float) $data['created_at'],
                'updated_at' => $data['updated_at'] ? (float) $data['updated_at'] : null,
                'completed_at' => $data['completed_at'] ? (float) $data['completed_at'] : null,
                'retry_count' => (int) $data['retry_count'],
            ]);
        }

        return $records;
    }
}

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
    public function begin(): void
    {
        parent::begin();
        $this->saveState();
    }

    /**
     * 提交事务
     */
    public function commit(): void
    {
        $this->updateStatus(TransactionRecord::STATUS_COMMITTING);
        parent::commit();
        $this->updateStatus(TransactionRecord::STATUS_COMMITTED);
    }

    /**
     * 回滚事务
     */
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
