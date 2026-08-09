<?php

declare(strict_types=1);

namespace Kode\Fibers\Transaction;

/**
 * 数据库存储
 */
class DatabaseTransactionStorage implements TransactionStorageInterface
{
    protected \PDO $pdo;
    protected string $table;

    public function __construct(\PDO $pdo, string $table = 'transactions')
    {
        // 表名仅允许安全字符，杜绝 SQL 标识符注入
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException(sprintf('非法的表名：%s', $table));
        }

        $this->pdo = $pdo;
        $this->table = $table;
        $this->initTable();
    }

    protected function driver(): string
    {
        return (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    protected function initTable(): void
    {
        // MySQL 支持建表内联索引；SQLite / PostgreSQL 需单独 CREATE INDEX。
        if ($this->driver() === 'mysql') {
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
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id VARCHAR(64) PRIMARY KEY,
            mode VARCHAR(16) NOT NULL,
            status VARCHAR(32) NOT NULL,
            participants TEXT,
            log TEXT,
            created_at DOUBLE PRECISION NOT NULL,
            updated_at DOUBLE PRECISION,
            completed_at DOUBLE PRECISION,
            retry_count INTEGER DEFAULT 0
        )";
        $this->pdo->exec($sql);
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS {$this->table}_idx_status ON {$this->table} (status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS {$this->table}_idx_created_at ON {$this->table} (created_at)");
    }

    public function save(TransactionRecord $record): bool
    {
        $columns = 'id, mode, status, participants, log, created_at, updated_at, completed_at, retry_count';
        $placeholders = ':id, :mode, :status, :participants, :log, :created_at, :updated_at, :completed_at, :retry_count';
        $update = 'status = :status, participants = :participants, log = :log, '
            . 'updated_at = :updated_at, completed_at = :completed_at, retry_count = :retry_count';

        // MySQL 使用 ON DUPLICATE KEY UPDATE；SQLite / PostgreSQL 使用 ON CONFLICT。
        $sql = $this->driver() === 'mysql'
            ? "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$update}"
            : "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders}) ON CONFLICT(id) DO UPDATE SET {$update}";

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
            'updated_at' => $data['updated_at'] !== null ? (float) $data['updated_at'] : null,
            'completed_at' => $data['completed_at'] !== null ? (float) $data['completed_at'] : null,
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
