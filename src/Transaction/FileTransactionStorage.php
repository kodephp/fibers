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
        return $this->path . '/' . $id . '.json';
    }
}
