<?php

declare(strict_types=1);

namespace Kode\Fibers\Core;

use Kode\Fibers\Contracts\LoadBalancerInterface;

/**
 * 多策略负载均衡器
 *
 * 支持：
 * - 轮询（Round Robin）
 * - 最小连接（Least Connections）
 * - 加权（Weighted）
 * - 随机（Random）
 * - IP Hash
 */
class AdvancedLoadBalancer implements LoadBalancerInterface
{
    public const STRATEGY_ROUND_ROBIN = 'round_robin';
    public const STRATEGY_LEAST_CONNECTIONS = 'least_connections';
    public const STRATEGY_WEIGHTED = 'weighted';
    public const STRATEGY_RANDOM = 'random';
    public const STRATEGY_IP_HASH = 'ip_hash';

    protected int $cursor = 0;
    protected string $strategy = self::STRATEGY_ROUND_ROBIN;
    protected array $nodeStats = [];
    protected array $weights = [];

    public function __construct(string $strategy = self::STRATEGY_ROUND_ROBIN)
    {
        $this->strategy = $strategy;
    }

    public function setStrategy(string $strategy): self
    {
        $this->strategy = $strategy;
        return $this;
    }

    public function setWeights(array $weights): self
    {
        $this->weights = $weights;
        return $this;
    }

    public function setNodeStats(string $nodeId, array $stats): self
    {
        $this->nodeStats[$nodeId] = array_merge([
            'active_connections' => 0,
            'total_requests' => 0,
            'failed_requests' => 0,
            'avg_response_time' => 0,
        ], $stats);
        return $this;
    }

    public function recordConnection(string $nodeId, bool $success = true, float $responseTime = 0): self
    {
        if (!isset($this->nodeStats[$nodeId])) {
            $this->nodeStats[$nodeId] = [
                'active_connections' => 0,
                'total_requests' => 0,
                'failed_requests' => 0,
                'avg_response_time' => 0,
            ];
        }

        $stats = &$this->nodeStats[$nodeId];
        $stats['total_requests']++;
        
        if ($success) {
            if ($responseTime > 0) {
                $n = $stats['total_requests'];
                $stats['avg_response_time'] = (($n - 1) * $stats['avg_response_time'] + $responseTime) / $n;
            }
        } else {
            $stats['failed_requests']++;
        }

        return $this;
    }

    public function incrementConnections(string $nodeId): self
    {
        if (!isset($this->nodeStats[$nodeId])) {
            $this->nodeStats[$nodeId] = [
                'active_connections' => 0,
                'total_requests' => 0,
                'failed_requests' => 0,
                'avg_response_time' => 0,
            ];
        }
        $this->nodeStats[$nodeId]['active_connections']++;
        return $this;
    }

    public function decrementConnections(string $nodeId): self
    {
        if (isset($this->nodeStats[$nodeId])) {
            $this->nodeStats[$nodeId]['active_connections'] = max(
                0,
                $this->nodeStats[$nodeId]['active_connections'] - 1
            );
        }
        return $this;
    }

    public function nextNode(array $nodes): string|int|null
    {
        if ($nodes === []) {
            return null;
        }

        if (count($nodes) === 1) {
            $keys = array_keys($nodes);
            return $keys[0];
        }

        return match ($this->strategy) {
            self::STRATEGY_LEAST_CONNECTIONS => $this->selectLeastConnections($nodes),
            self::STRATEGY_WEIGHTED => $this->selectWeighted($nodes),
            self::STRATEGY_RANDOM => $this->selectRandom($nodes),
            self::STRATEGY_IP_HASH => $this->selectIpHash($nodes),
            default => $this->selectRoundRobin($nodes),
        };
    }

    public function selectNodes(array $nodes, int $count): array
    {
        if ($count >= count($nodes)) {
            return array_keys($nodes);
        }

        $selected = [];
        $nodesCopy = $nodes;

        for ($i = 0; $i < $count; $i++) {
            $nodeId = $this->nextNode($nodesCopy);
            if ($nodeId !== null) {
                $selected[] = $nodeId;
                unset($nodesCopy[(string) $nodeId]);
            }
        }

        return $selected;
    }

    public function distribute(array $items, int $workers): array
    {
        $workers = max(1, $workers);
        $buckets = array_fill(0, $workers, []);

        if ($items === []) {
            return $buckets;
        }

        $nodeIds = array_keys($items);
        $index = 0;

        foreach ($items as $key => $item) {
            $target = $index % $workers;
            $buckets[$target][$key] = $item;
            $index++;
        }

        return $buckets;
    }

    public function getNodeStats(string $nodeId): array
    {
        return $this->nodeStats[$nodeId] ?? [
            'active_connections' => 0,
            'total_requests' => 0,
            'failed_requests' => 0,
            'avg_response_time' => 0,
        ];
    }

    public function getAllStats(): array
    {
        return $this->nodeStats;
    }

    public function getHealthiestNode(array $nodes): string|int|null
    {
        if ($nodes === []) {
            return null;
        }

        $healthiest = null;
        $lowestLoad = PHP_INT_MAX;

        foreach ($nodes as $nodeId => $node) {
            $stats = $this->getNodeStats((string) $nodeId);
            $load = $this->calculateNodeLoad((string) $nodeId, $stats);
            
            if ($load < $lowestLoad) {
                $lowestLoad = $load;
                $healthiest = (string) $nodeId;
            }
        }

        return $healthiest;
    }

    protected function selectRoundRobin(array $nodes): string|int|null
    {
        $keys = array_keys($nodes);
        $index = $this->cursor % count($keys);
        $this->cursor++;
        return $keys[$index];
    }

    protected function selectLeastConnections(array $nodes): string|int|null
    {
        $selected = null;
        $lowestConnections = PHP_INT_MAX;

        foreach ($nodes as $nodeId => $node) {
            $stats = $this->getNodeStats((string) $nodeId);
            $connections = $stats['active_connections'];

            if ($connections < $lowestConnections) {
                $lowestConnections = $connections;
                $selected = (string) $nodeId;
            }
        }

        return $selected;
    }

    protected function selectWeighted(array $nodes): string|int|null
    {
        $totalWeight = 0;
        $weightedNodes = [];

        foreach ($nodes as $nodeId => $node) {
            $weight = $this->weights[(string) $nodeId] ?? 1;
            $totalWeight += $weight;
            $weightedNodes[] = ['node_id' => $nodeId, 'weight' => $weight, 'max' => $totalWeight];
        }

        if ($totalWeight === 0) {
            return $this->selectRoundRobin($nodes);
        }

        $rand = mt_rand(1, $totalWeight);

        foreach ($weightedNodes as $weighted) {
            if ($rand <= $weighted['max']) {
                return $weighted['node_id'];
            }
        }

        return array_keys($nodes)[0];
    }

    protected function selectRandom(array $nodes): string|int|null
    {
        $keys = array_keys($nodes);
        return $keys[mt_rand(0, count($keys) - 1)];
    }

    protected function selectIpHash(array $nodes): string|int|null
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $hash = crc32($clientIp);
        $keys = array_keys($nodes);
        $index = $hash % count($keys);
        return $keys[$index];
    }

    protected function calculateNodeLoad(string $nodeId, array $stats): float
    {
        $activeConnections = $stats['active_connections'] ?? 0;
        $failedRequests = $stats['failed_requests'] ?? 0;
        $totalRequests = $stats['total_requests'] ?? 1;
        $avgResponseTime = $stats['avg_response_time'] ?? 0;
        $weight = $this->weights[$nodeId] ?? 1;

        $failureRate = $totalRequests > 0 ? $failedRequests / $totalRequests : 0;

        return ($activeConnections / max(1, $weight)) 
            + ($failureRate * 100) 
            + ($avgResponseTime / 100);
    }

    public function reset(): self
    {
        $this->cursor = 0;
        $this->nodeStats = [];
        return $this;
    }

    public function resetNode(string $nodeId): self
    {
        unset($this->nodeStats[$nodeId]);
        return $this;
    }
}
