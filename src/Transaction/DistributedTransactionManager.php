<?php

declare(strict_types=1);

namespace Kode\Fibers\Transaction;

/**
 * 分布式事务管理器
 *
 * 支持：
 * - 两阶段提交（2PC）
 * - TCC（Try-Confirm-Cancel）模式
 * - saga 模式
 */
class DistributedTransactionManager
{
    protected array $participants = [];
    protected array $transactionLog = [];
    protected string $transactionId;
    protected string $mode;
    protected int $timeout;
    protected bool $committed = false;
    protected bool $rolledBack = false;

    public const MODE_2PC = '2pc';
    public const MODE_TCC = 'tcc';
    public const MODE_SAGA = 'saga';

    public function __construct(string $mode = self::MODE_2PC, int $timeout = 30)
    {
        $this->transactionId = $this->generateId();
        $this->mode = $mode;
        $this->timeout = $timeout;
    }

    /**
     * 注册参与者
     */
    public function register(string $resourceId, callable $prepare, callable $commit, callable $rollback): self
    {
        $this->participants[$resourceId] = [
            'prepare' => $prepare,
            'commit' => $commit,
            'rollback' => $rollback,
            'status' => 'pending',
        ];
        
        return $this;
    }

    /**
     * 执行事务
     */
    public function execute(callable $action): mixed
    {
        try {
            $this->begin();
            
            $result = $action($this);
            
            $this->commit();
            
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * 开始事务
     */
    public function begin(): void
    {
        $this->log('begin', ['transaction_id' => $this->transactionId]);
        
        foreach ($this->participants as $resourceId => &$participant) {
            try {
                $result = ($participant['prepare'])();
                $participant['status'] = 'prepared';
                $this->log('prepare_ok', ['resource_id' => $resourceId]);
            } catch (\Throwable $e) {
                $participant['status'] = 'prepare_failed';
                $this->log('prepare_failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);
                throw $e;
            }
        }
    }

    /**
     * 提交事务
     */
    public function commit(): void
    {
        if ($this->committed) {
            return;
        }

        $this->log('commit_start');
        
        $failed = [];
        
        foreach ($this->participants as $resourceId => &$participant) {
            if ($participant['status'] !== 'prepared') {
                continue;
            }
            
            try {
                ($participant['commit'])();
                $participant['status'] = 'committed';
                $this->log('commit_ok', ['resource_id' => $resourceId]);
            } catch (\Throwable $e) {
                $participant['status'] = 'commit_failed';
                $failed[$resourceId] = $e;
                $this->log('commit_failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);
            }
        }
        
        if (!empty($failed)) {
            $this->log('commit_partial', ['failed' => array_keys($failed)]);
        }
        
        $this->committed = true;
        $this->log('commit_complete');
    }

    /**
     * 回滚事务
     */
    public function rollback(): void
    {
        if ($this->rolledBack) {
            return;
        }

        $this->log('rollback_start');
        
        foreach ($this->participants as $resourceId => &$participant) {
            if ($participant['status'] === 'committed') {
                continue;
            }
            
            try {
                ($participant['rollback'])();
                $participant['status'] = 'rolled_back';
                $this->log('rollback_ok', ['resource_id' => $resourceId]);
            } catch (\Throwable $e) {
                $participant['status'] = 'rollback_failed';
                $this->log('rollback_failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);
            }
        }
        
        $this->rolledBack = true;
        $this->log('rollback_complete');
    }

    /**
     * TCC 模式 - Try
     */
    public function try(string $resourceId, callable $try): bool
    {
        if (!isset($this->participants[$resourceId])) {
            return false;
        }
        
        try {
            $result = $try();
            $this->participants[$resourceId]['try_result'] = $result;
            $this->participants[$resourceId]['status'] = 'try_ok';
            return true;
        } catch (\Throwable $e) {
            $this->participants[$resourceId]['status'] = 'try_failed';
            return false;
        }
    }

    /**
     * TCC 模式 - Confirm
     */
    public function confirm(string $resourceId): bool
    {
        if (!isset($this->participants[$resourceId])) {
            return false;
        }
        
        $participant = &$this->participants[$resourceId];
        
        if ($participant['status'] !== 'try_ok') {
            return false;
        }
        
        try {
            ($participant['commit'])();
            $participant['status'] = 'confirmed';
            return true;
        } catch (\Throwable $e) {
            $participant['status'] = 'confirm_failed';
            return false;
        }
    }

    /**
     * TCC 模式 - Cancel
     */
    public function cancel(string $resourceId): bool
    {
        if (!isset($this->participants[$resourceId])) {
            return false;
        }
        
        $participant = &$this->participants[$resourceId];
        
        try {
            ($participant['rollback'])();
            $participant['status'] = 'cancelled';
            return true;
        } catch (\Throwable $e) {
            $participant['status'] = 'cancel_failed';
            return false;
        }
    }

    /**
     * Saga 模式 - 添加步骤
     */
    public function addSagaStep(string $stepId, callable $action, ?callable $compensation = null): self
    {
        $this->participants[$stepId] = [
            'action' => $action,
            'compensation' => $compensation,
            'status' => 'pending',
        ];
        
        return $this;
    }

    /**
     * 执行 Saga
     */
    public function executeSaga(): mixed
    {
        $executed = [];
        $result = null;
        
        try {
            foreach ($this->participants as $stepId => &$participant) {
                $result = ($participant['action'])();
                $participant['status'] = 'completed';
                $executed[] = $stepId;
            }
            
            $this->committed = true;
            return $result;
        } catch (\Throwable $e) {
            foreach (array_reverse($executed) as $stepId) {
                $participant = &$this->participants[$stepId];
                
                if ($participant['compensation']) {
                    try {
                        ($participant['compensation'])();
                        $participant['status'] = 'compensated';
                    } catch (\Throwable $ce) {
                        $participant['status'] = 'compensation_failed';
                    }
                }
            }
            
            throw $e;
        }
    }

    /**
     * 获取事务 ID
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * 获取参与者状态
     */
    public function getStatus(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'mode' => $this->mode,
            'committed' => $this->committed,
            'rolled_back' => $this->rolledBack,
            'participants' => array_map(fn($p) => $p['status'], $this->participants),
        ];
    }

    /**
     * 获取事务日志
     */
    public function getLog(): array
    {
        return $this->transactionLog;
    }

    /**
     * 记录日志
     */
    protected function log(string $event, array $data = []): void
    {
        $this->transactionLog[] = [
            'event' => $event,
            'timestamp' => microtime(true),
            'data' => $data,
        ];
    }

    /**
     * 生成事务 ID
     */
    protected function generateId(): string
    {
        return sprintf(
            'txn_%s_%s',
            date('YmdHis'),
            bin2hex(random_bytes(8))
        );
    }
}
