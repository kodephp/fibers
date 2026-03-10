<?php

declare(strict_types=1);

namespace Kode\Fibers\Core;

/**
 * 分布式任务分配器
 *
 * 负责根据节点健康状态和负载策略进行任务分配。
 */
class DistributedScheduler
{
    protected array $nodes = [];
    protected RoundRobinBalancer $balancer;

    public function __construct(?RoundRobinBalancer $balancer = null)
    {
        $this->balancer = $balancer ?? new RoundRobinBalancer();
    }

    public function registerNode(string $nodeId, array $meta = []): void
    {
        $this->nodes[$nodeId] = array_merge([
            'weight' => 1,
            'healthy' => true,
            'tags' => [],
        ], $meta);
    }

    public function unregisterNode(string $nodeId): void
    {
        unset($this->nodes[$nodeId]);
    }

    public function setNodeHealth(string $nodeId, bool $healthy): void
    {
        if (!isset($this->nodes[$nodeId])) {
            return;
        }

        $this->nodes[$nodeId]['healthy'] = $healthy;
    }

    public function listNodes(): array
    {
        return $this->nodes;
    }

    public function healthyNodes(): array
    {
        return array_filter(
            $this->nodes,
            static fn(array $node): bool => (bool) ($node['healthy'] ?? true)
        );
    }

    public function dispatch(array $tasks): array
    {
        $healthy = $this->healthyNodes();
        if ($healthy === []) {
            return ['assignments' => [], 'unassigned' => $tasks];
        }

        $assignments = [];
        $unassigned = [];

        foreach ($tasks as $taskId => $taskPayload) {
            $candidateNodeIds = $this->resolveCandidates($healthy, $taskPayload);
            if ($candidateNodeIds === []) {
                $unassigned[$taskId] = $taskPayload;
                continue;
            }

            $selectedIndex = $this->balancer->nextNode($candidateNodeIds);
            if ($selectedIndex === null) {
                $unassigned[$taskId] = $taskPayload;
                continue;
            }
            $nodeId = $candidateNodeIds[(int) $selectedIndex] ?? (string) $selectedIndex;
            $assignments[$nodeId][$taskId] = $taskPayload;
        }

        return ['assignments' => $assignments, 'unassigned' => $unassigned];
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
}
