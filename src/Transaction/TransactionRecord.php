<?php

declare(strict_types=1);

namespace Kode\Fibers\Transaction;

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
