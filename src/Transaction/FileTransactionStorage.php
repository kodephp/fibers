<?php

declare(strict_types=1);

namespace Kode\Fibers\Transaction;

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
        // 仅允许安全的文件名（字母/数字/短横/下划线/点），杜绝路径穿越（../）。
        // basename 兜底剥离任何目录成分，确保文件始终落在本存储目录下。
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
            throw new \InvalidArgumentException(sprintf('非法的事务 ID：%s', $id));
        }

        return $this->path . '/' . basename($id . '.json');
    }
}
