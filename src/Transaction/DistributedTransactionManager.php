<?php

declare(strict_types=1);

namespace Kode\Fibers\Transaction;

use Kode\Fibers\Exceptions\FiberException;

/**
 * 分布式事务管理器
 *
 * 支持：
 * - 两阶段提交（2PC）
 * - TCC（Try-Confirm-Cancel）模式
 * - Saga 模式
 *
 * v4 起 2PC/TCC 参与者与 Saga 步骤使用**独立存储**：
 * 旧版本二者共用同一个数组，一旦混用就会因为缺少 prepare/commit 键而崩溃。
 */
class DistributedTransactionManager
{
    /**
     * 2PC / TCC 参与者
     *
     * @var array<string, array{prepare: callable, commit: callable, rollback: callable, status: string, try_result?: mixed}>
     */
    protected array $participants = [];

    /**
     * Saga 步骤（有序）
     *
     * @var array<string, array{action: callable, compensation: callable|null, status: string, result?: mixed}>
     */
    protected array $sagaSteps = [];

    protected array $transactionLog = [];

    protected string $transactionId;

    protected string $mode;

    protected int $timeout;

    protected bool $committed = false;

    protected bool $rolledBack = false;

    public const MODE_2PC = '2pc';
    public const MODE_TCC = 'tcc';
    public const MODE_SAGA = 'saga';

    /**
     * 支持的模式
     */
    public const MODES = [self::MODE_2PC, self::MODE_TCC, self::MODE_SAGA];

    public function __construct(string $mode = self::MODE_2PC, int $timeout = 30)
    {
        if (!in_array($mode, self::MODES, true)) {
            throw new FiberException(sprintf(
                '未知的事务模式 [%s]，可选值：%s',
                $mode,
                implode(', ', self::MODES)
            ));
        }

        $this->transactionId = $this->generateId();
        $this->mode = $mode;
        $this->timeout = $timeout;
    }

    /**
     * 注册 2PC / TCC 参与者
     */
    public function register(string $resourceId, callable $prepare, callable $commit, callable $rollback): self
    {
        if ($this->mode === self::MODE_SAGA) {
            throw new FiberException('Saga 模式请使用 addSagaStep() 注册步骤');
        }

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
     *
     * Saga 模式下直接驱动已登记的步骤；其余模式走 begin → action → commit。
     */
    public function execute(?callable $action = null): mixed
    {
        if ($this->mode === self::MODE_SAGA) {
            if ($action !== null) {
                $action($this);
            }

            return $this->executeSaga();
        }

        try {
            $this->begin();

            $result = $action === null ? null : $action($this);

            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollback();

            throw $e;
        }
    }

    /**
     * 开始事务（2PC prepare 阶段）
     */
    public function begin(): void
    {
        $this->log('begin', ['transaction_id' => $this->transactionId]);

        foreach (array_keys($this->participants) as $resourceId) {
            try {
                ($this->participants[$resourceId]['prepare'])();
                $this->participants[$resourceId]['status'] = 'prepared';
                $this->log('prepare_ok', ['resource_id' => $resourceId]);
            } catch (\Throwable $e) {
                $this->participants[$resourceId]['status'] = 'prepare_failed';
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

        foreach (array_keys($this->participants) as $resourceId) {
            if ($this->participants[$resourceId]['status'] !== 'prepared') {
                continue;
            }

            try {
                ($this->participants[$resourceId]['commit'])();
                $this->participants[$resourceId]['status'] = 'committed';
                $this->log('commit_ok', ['resource_id' => $resourceId]);
            } catch (\Throwable $e) {
                $this->participants[$resourceId]['status'] = 'commit_failed';
                $failed[$resourceId] = $e;
                $this->log('commit_failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);
            }
        }

        if ($failed !== []) {
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

        foreach (array_keys($this->participants) as $resourceId) {
            if ($this->participants[$resourceId]['status'] === 'committed') {
                continue;
            }

            try {
                ($this->participants[$resourceId]['rollback'])();
                $this->participants[$resourceId]['status'] = 'rolled_back';
                $this->log('rollback_ok', ['resource_id' => $resourceId]);
            } catch (\Throwable $e) {
                $this->participants[$resourceId]['status'] = 'rollback_failed';
                $this->log('rollback_failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);
            }
        }

        // Saga 步骤同样需要补偿
        if ($this->sagaSteps !== []) {
            $this->compensate(array_keys(array_filter(
                $this->sagaSteps,
                static fn(array $step): bool => $step['status'] === 'completed'
            )));
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
            $this->participants[$resourceId]['try_result'] = $try();
            $this->participants[$resourceId]['status'] = 'try_ok';
            $this->log('try_ok', ['resource_id' => $resourceId]);

            return true;
        } catch (\Throwable $e) {
            $this->participants[$resourceId]['status'] = 'try_failed';
            $this->log('try_failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);

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

        if ($this->participants[$resourceId]['status'] !== 'try_ok') {
            return false;
        }

        try {
            ($this->participants[$resourceId]['commit'])();
            $this->participants[$resourceId]['status'] = 'confirmed';
            $this->log('confirm_ok', ['resource_id' => $resourceId]);

            return true;
        } catch (\Throwable $e) {
            $this->participants[$resourceId]['status'] = 'confirm_failed';
            $this->log('confirm_failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);

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

        try {
            ($this->participants[$resourceId]['rollback'])();
            $this->participants[$resourceId]['status'] = 'cancelled';
            $this->log('cancel_ok', ['resource_id' => $resourceId]);

            return true;
        } catch (\Throwable $e) {
            $this->participants[$resourceId]['status'] = 'cancel_failed';
            $this->log('cancel_failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Saga 模式 - 添加步骤
     */
    public function addSagaStep(string $stepId, callable $action, ?callable $compensation = null): self
    {
        $this->sagaSteps[$stepId] = [
            'action' => $action,
            'compensation' => $compensation,
            'status' => 'pending',
        ];

        return $this;
    }

    /**
     * 执行 Saga：任一步骤失败时按逆序补偿已完成的步骤
     *
     * @return mixed 最后一个步骤的返回值
     */
    public function executeSaga(): mixed
    {
        $executed = [];
        $result = null;

        foreach (array_keys($this->sagaSteps) as $stepId) {
            try {
                $result = ($this->sagaSteps[$stepId]['action'])();
                $this->sagaSteps[$stepId]['result'] = $result;
                $this->sagaSteps[$stepId]['status'] = 'completed';
                $executed[] = $stepId;
                $this->log('saga_step_ok', ['step_id' => $stepId]);
            } catch (\Throwable $e) {
                $this->sagaSteps[$stepId]['status'] = 'failed';
                $this->log('saga_step_failed', ['step_id' => $stepId, 'error' => $e->getMessage()]);

                $this->compensate(array_reverse($executed));
                $this->rolledBack = true;

                throw $e;
            }
        }

        $this->committed = true;
        $this->log('saga_complete');

        return $result;
    }

    /**
     * 获取各 Saga 步骤的返回值
     *
     * @return array<string, mixed>
     */
    public function sagaResults(): array
    {
        $results = [];

        foreach ($this->sagaSteps as $stepId => $step) {
            if (array_key_exists('result', $step)) {
                $results[$stepId] = $step['result'];
            }
        }

        return $results;
    }

    /**
     * 获取事务 ID
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * 事务模式
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * 超时秒数
     */
    public function getTimeout(): int
    {
        return $this->timeout;
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
            'participants' => array_map(static fn(array $p): string => $p['status'], $this->participants),
            'saga_steps' => array_map(static fn(array $s): string => $s['status'], $this->sagaSteps),
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
     * 按给定顺序补偿 Saga 步骤
     *
     * @param string[] $stepIds
     */
    protected function compensate(array $stepIds): void
    {
        foreach ($stepIds as $stepId) {
            if (!isset($this->sagaSteps[$stepId])) {
                continue;
            }

            $compensation = $this->sagaSteps[$stepId]['compensation'];

            if ($compensation === null) {
                $this->sagaSteps[$stepId]['status'] = 'no_compensation';

                continue;
            }

            try {
                $compensation();
                $this->sagaSteps[$stepId]['status'] = 'compensated';
                $this->log('saga_compensated', ['step_id' => $stepId]);
            } catch (\Throwable $e) {
                $this->sagaSteps[$stepId]['status'] = 'compensation_failed';
                $this->log('saga_compensation_failed', ['step_id' => $stepId, 'error' => $e->getMessage()]);
            }
        }
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
