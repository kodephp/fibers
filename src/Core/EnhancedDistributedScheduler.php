<?php

declare(strict_types=1);

namespace Kode\Fibers\Core;

use Kode\Fibers\Contracts\NodeTransportInterface;

/**
 * 增强型分布式任务调度器
 *
 * 支持：
 * - 任务回执（Acknowledgment）
 * - 失败重试转移
 * - 多级负载均衡策略
 * - 服务维度断路器
 */
class EnhancedDistributedScheduler
{
    protected array $nodes = [];
    protected RoundRobinBalancer $balancer;
    protected ?NodeTransportInterface $transport;
    protected array $taskReceipts = [];
    protected array $retryHistory = [];
    protected array $circuitBreakers = [];
    protected array $config;

    public function __construct(
        ?RoundRobinBalancer $balancer = null,
        ?NodeTransportInterface $transport = null,
        array $config = []
    ) {
        $this->balancer = $balancer ?? new RoundRobinBalancer();
        $this->transport = $transport;
        $this->config = array_merge([
            'max_retries' => 3,
            'retry_delay_ms' => 100,
            'receipt_timeout_ms' => 30000,
            'failover_enabled' => true,
            'circuit_breaker_threshold' => 5,
            'circuit_breaker_timeout' => 60,
        ], $config);
    }

    public function registerNode(string $nodeId, array $meta = []): void
    {
        $this->nodes[$nodeId] = array_merge([
            'weight' => 1,
            'healthy' => true,
            'tags' => [],
            'active_connections' => 0,
            'max_connections' => 100,
            'services' => [],
        ], $meta);
        
        $this->circuitBreakers[$nodeId] = new CircuitBreaker(
            $this->config['circuit_breaker_threshold'],
            $this->config['circuit_breaker_timeout']
        );
    }

    public function unregisterNode(string $nodeId): void
    {
        unset($this->nodes[$nodeId], $this->circuitBreakers[$nodeId]);
    }

    public function setNodeHealth(string $nodeId, bool $healthy): void
    {
        if (!isset($this->nodes[$nodeId])) {
            return;
        }
        $this->nodes[$nodeId]['healthy'] = $healthy;
    }

    public function setNodeConnectionCount(string $nodeId, int $count): void
    {
        if (!isset($this->nodes[$nodeId])) {
            return;
        }
        $this->nodes[$nodeId]['active_connections'] = $count;
    }

    public function updateNodeServiceStatus(string $nodeId, string $service, bool $available): void
    {
        if (!isset($this->nodes[$nodeId])) {
            return;
        }
        $this->nodes[$nodeId]['services'][$service] = $available;
        
        $circuit = $this->getCircuitBreaker($nodeId);
        if ($available) {
            $circuit->recordSuccess();
        } else {
            $circuit->recordFailure();
        }
    }

    public function getCircuitBreaker(string $nodeId): ?CircuitBreaker
    {
        return $this->circuitBreakers[$nodeId] ?? null;
    }

    public function listNodes(): array
    {
        return $this->nodes;
    }

    public function healthyNodes(?string $service = null): array
    {
        return array_filter($this->nodes, function (array $node) use ($service) {
            if (!($node['healthy'] ?? true)) {
                return false;
            }
            
            $circuit = $this->getCircuitBreaker(array_search($node, $this->nodes) ?: '');
            if ($circuit && $circuit->state() === CircuitBreaker::STATE_OPEN) {
                return false;
            }
            
            if ($service !== null) {
                return ($node['services'][$service] ?? false) === true;
            }
            
            return true;
        });
    }

    /**
     * 分发任务并跟踪回执
     *
     * @param array $tasks 任务列表
     * @param string $receiptCallback 回执回调
     * @return array 分配结果
     */
    public function dispatchWithReceipt(array $tasks, ?callable $receiptCallback = null): array
    {
        $healthy = $this->healthyNodes();
        if ($healthy === []) {
            return [
                'assignments' => [],
                'unassigned' => $tasks,
                'failed' => [],
            ];
        }

        $assignments = [];
        $unassigned = [];
        $failed = [];

        foreach ($tasks as $taskId => $taskPayload) {
            $attempt = $this->retryHistory[$taskId]['attempts'] ?? 0;
            $maxRetries = $this->config['max_retries'];
            
            if ($attempt >= $maxRetries) {
                $failed[$taskId] = array_merge($taskPayload, [
                    'error' => 'Max retries exceeded',
                    'attempts' => $attempt,
                ]);
                continue;
            }

            $candidateNodeIds = $this->resolveCandidates($healthy, $taskPayload);
            if ($candidateNodeIds === []) {
                if ($this->config['failover_enabled']) {
                    $unassigned[$taskId] = $taskPayload;
                } else {
                    $failed[$taskId] = array_merge($taskPayload, [
                        'error' => 'No available nodes',
                        'attempts' => $attempt,
                    ]);
                }
                continue;
            }

            $selectedIndex = $this->balancer->nextNode($candidateNodeIds);
            if ($selectedIndex === null) {
                $unassigned[$taskId] = $taskPayload;
                continue;
            }
            
            $nodeId = is_numeric($selectedIndex) 
                ? ($candidateNodeIds[(int) $selectedIndex] ?? $selectedIndex)
                : $selectedIndex;
            
            $receiptId = $this->generateReceiptId($taskId, $nodeId);
            
            $assignments[$nodeId][$receiptId] = array_merge($taskPayload, [
                'original_task_id' => $taskId,
                'attempt' => $attempt,
                'receipt_id' => $receiptId,
                'timestamp' => microtime(true),
            ]);
            
            $this->taskReceipts[$receiptId] = [
                'task_id' => $taskId,
                'node_id' => $nodeId,
                'status' => 'pending',
                'created_at' => time(),
            ];
            
            if ($receiptCallback) {
                $receiptCallback($receiptId, $taskPayload);
            }
        }

        return [
            'assignments' => $assignments,
            'unassigned' => $unassigned,
            'failed' => $failed,
            'receipts' => array_keys($this->taskReceipts),
        ];
    }

    /**
     * 处理任务回执
     *
     * @param string $receiptId 回执ID
     * @param array $result 执行结果
     * @return void
     */
    public function handleReceipt(string $receiptId, array $result): void
    {
        if (!isset($this->taskReceipts[$receiptId])) {
            return;
        }

        $receipt = &$this->taskReceipts[$receiptId];
        $receipt['status'] = $result['status'] ?? 'completed';
        $receipt['result'] = $result;
        $receipt['completed_at'] = time();

        if ($result['status'] === 'failed' && ($result['retry'] ?? false)) {
            $this->scheduleRetry($receipt['task_id'], $result);
        }
    }

    /**
     * 标记任务失败并可能触发重试
     *
     * @param string $receiptId 回执ID
     * @param \Throwable $error 错误信息
     * @return bool 是否触发重试
     */
    public function handleFailure(string $receiptId, \Throwable $error): bool
    {
        if (!isset($this->taskReceipts[$receiptId])) {
            return false;
        }

        $receipt = &$this->taskReceipts[$receiptId];
        $taskId = $receipt['task_id'];
        $nodeId = $receipt['node_id'];

        $this->retryHistory[$taskId]['attempts'] = ($this->retryHistory[$taskId]['attempts'] ?? 0) + 1;
        $this->retryHistory[$taskId]['last_error'] = $error->getMessage();
        $this->retryHistory[$taskId]['last_failed_node'] = $nodeId;

        $circuit = $this->getCircuitBreaker($nodeId);
        if ($circuit) {
            $circuit->recordFailure();
        }

        $maxRetries = $this->config['max_retries'];
        if ($this->retryHistory[$taskId]['attempts'] < $maxRetries) {
            return true;
        }

        $receipt['status'] = 'exhausted';
        return false;
    }

    /**
     * 调度重试
     *
     * @param string $taskId 任务ID
     * @param array $lastResult 上次结果
     * @return void
     */
    protected function scheduleRetry(string $taskId, array $lastResult): void
    {
        $delayMs = $this->config['retry_delay_ms'];
        $delaySec = $delayMs / 1000;
        
        $this->retryHistory[$taskId]['retry_scheduled'] = true;
        $this->retryHistory[$taskId]['retry_at'] = microtime(true) + $delaySec;
    }

    /**
     * 获取待重试的任务
     *
     * @return array
     */
    public function getPendingRetries(): array
    {
        $now = microtime(true);
        $pending = [];

        foreach ($this->retryHistory as $taskId => $history) {
            if (!($history['retry_scheduled'] ?? false)) {
                continue;
            }
            
            if (($history['retry_at'] ?? 0) <= $now) {
                $pending[$taskId] = $history;
                $this->retryHistory[$taskId]['retry_scheduled'] = false;
            }
        }

        return $pending;
    }

    /**
     * 获取任务回执状态
     *
     * @param string|null $taskId 任务ID
     * @return array
     */
    public function getReceiptStatus(?string $taskId = null): array
    {
        if ($taskId !== null) {
            return array_filter(
                $this->taskReceipts,
                fn($r) => $r['task_id'] === $taskId
            );
        }
        return $this->taskReceipts;
    }

    /**
     * 获取节点指标
     *
     * @param string $nodeId 节点ID
     * @return array
     */
    public function getNodeMetrics(string $nodeId): array
    {
        if (!isset($this->nodes[$nodeId])) {
            return [];
        }

        $node = $this->nodes[$nodeId];
        $circuit = $this->getCircuitBreaker($nodeId);

        return [
            'node_id' => $nodeId,
            'healthy' => $node['healthy'] ?? false,
            'circuit_state' => $circuit?->state(),
            'active_connections' => $node['active_connections'] ?? 0,
            'max_connections' => $node['max_connections'] ?? 100,
            'weight' => $node['weight'] ?? 1,
            'load_factor' => ($node['active_connections'] ?? 0) / max(1, $node['max_connections'] ?? 100),
            'services' => $node['services'] ?? [],
        ];
    }

    /**
     * 生成回执ID
     *
     * @param string $taskId 任务ID
     * @param string $nodeId 节点ID
     * @return string
     */
    protected function generateReceiptId(string $taskId, string $nodeId): string
    {
        return sprintf(
            '%s:%s:%s',
            $nodeId,
            $taskId,
            bin2hex(random_bytes(8))
        );
    }

    protected function resolveCandidates(array $healthyNodes, mixed $taskPayload): array
    {
        if (!is_array($taskPayload) || !isset($taskPayload['required_tags'])) {
            return array_keys($healthyNodes);
        }

        $requiredTags = (array) $taskPayload['required_tags'];
        if ($requiredTags === []) {
            return array_keys($healthyNodes);
        }

        $candidateNodeIds = [];
        foreach ($healthyNodes as $nodeId => $nodeMeta) {
            $nodeTags = (array) ($nodeMeta['tags'] ?? []);
            $matched = array_diff($requiredTags, $nodeTags) === [];
            if ($matched) {
                $candidateNodeIds[] = $nodeId;
            }
        }

        return $candidateNodeIds;
    }

    /**
     * 负载均衡策略选择
     *
     * @param string $strategy 策略：round_robin, least_connections, weighted
     * @return string|null 选中的节点ID
     */
    public function selectNodeByStrategy(string $strategy, array $nodeIds): ?string
    {
        if ($nodeIds === []) {
            return null;
        }

        return match ($strategy) {
            'round_robin' => $this->balancer->nextNode($nodeIds),
            'least_connections' => $this->selectLeastConnectionsNode($nodeIds),
            'weighted' => $this->selectWeightedNode($nodeIds),
            default => $nodeIds[0] ?? null,
        };
    }

    protected function selectLeastConnectionsNode(array $nodeIds): ?string
    {
        $bestNode = null;
        $lowestLoad = PHP_INT_MAX;

        foreach ($nodeIds as $nodeId) {
            $metrics = $this->getNodeMetrics($nodeId);
            $load = $metrics['load_factor'] ?? 1;
            if ($load < $lowestLoad) {
                $lowestLoad = $load;
                $bestNode = $nodeId;
            }
        }

        return $bestNode;
    }

    protected function selectWeightedNode(array $nodeIds): ?string
    {
        $totalWeight = 0;
        $weightedNodes = [];

        foreach ($nodeIds as $nodeId) {
            $node = $this->nodes[$nodeId] ?? [];
            $weight = $node['weight'] ?? 1;
            $weightedNodes[] = ['node_id' => $nodeId, 'weight' => $weight];
            $totalWeight += $weight;
        }

        if ($totalWeight === 0) {
            return $nodeIds[0] ?? null;
        }

        $rand = mt_rand(1, $totalWeight);
        foreach ($weightedNodes as $weighted) {
            $rand -= $weighted['weight'];
            if ($rand <= 0) {
                return $weighted['node_id'];
            }
        }

        return $nodeIds[0];
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getStats(): array
    {
        return [
            'total_nodes' => count($this->nodes),
            'healthy_nodes' => count($this->healthyNodes()),
            'pending_receipts' => count(array_filter(
                $this->taskReceipts,
                fn($r) => $r['status'] === 'pending'
            )),
            'completed_receipts' => count(array_filter(
                $this->taskReceipts,
                fn($r) => $r['status'] === 'completed'
            )),
            'failed_tasks' => count($this->retryHistory),
            'circuit_breakers' => array_map(
                fn($cb) => $cb->metrics(),
                $this->circuitBreakers
            ),
        ];
    }
}
