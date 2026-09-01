<?php

namespace App\Services;

use App\Enums\NodeStatus;
use App\Exceptions\CapacityException;
use App\Models\Application;
use App\Models\Node;
use Illuminate\Support\Facades\DB;
use LogicException;

class NodeScheduler
{
    public function selectNodeForApplication(float $cpu, int $memoryMb, int $diskMb): Node
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Node selection must run inside a database transaction.');
        }

        $nodes = Node::query()
            ->where('status', NodeStatus::Active->value)
            ->orderByRaw('(memory_total_mb - memory_reserved_mb) DESC')
            ->orderByRaw('(disk_total_mb - disk_reserved_mb) DESC')
            ->orderBy('code')
            ->lockForUpdate()
            ->get();

        $node = $nodes->first(fn (Node $candidate): bool => $this->canAcceptApplication($candidate, $cpu, $memoryMb, $diskMb));
        if (! $node) {
            throw new CapacityException;
        }

        $node->update([
            'cpu_reserved' => round($node->cpu_reserved + $cpu, 2),
            'memory_reserved_mb' => $node->memory_reserved_mb + $memoryMb,
            'disk_reserved_mb' => $node->disk_reserved_mb + $diskMb,
        ]);

        return $node;
    }

    public function canAcceptApplication(Node $node, float $cpu, int $memoryMb, int $diskMb): bool
    {
        if ($node->status !== NodeStatus::Active || $this->isUnderPressure($node)) {
            return false;
        }

        return $node->cpu_total - config('nodes.platform_reserve.cpu') - $node->cpu_reserved >= $cpu
            && $node->memory_total_mb - config('nodes.platform_reserve.memory_mb') - $node->memory_reserved_mb >= $memoryMb
            && $node->disk_total_mb - config('nodes.platform_reserve.disk_mb') - $node->disk_reserved_mb >= $diskMb;
    }

    public function releaseAndDeleteApplication(Application $application, bool $force = false): void
    {
        DB::transaction(function () use ($application, $force): void {
            $node = Node::query()->lockForUpdate()->findOrFail($application->node_id);
            $node->update([
                'cpu_reserved' => max(0, round($node->cpu_reserved - (float) $application->cpu_limit, 2)),
                'memory_reserved_mb' => max(0, $node->memory_reserved_mb - $application->memory_limit_mb),
                'disk_reserved_mb' => max(0, $node->disk_reserved_mb - $application->disk_limit_mb),
            ]);
            $force ? $application->forceDelete() : $application->delete();
        }, 3);
    }

    public function markNodeDraining(Node $node): Node
    {
        return $this->setStatus($node, NodeStatus::Draining);
    }

    public function markNodeMaintenance(Node $node): Node
    {
        return $this->setStatus($node, NodeStatus::Maintenance);
    }

    public function activateNode(Node $node): Node
    {
        return $this->setStatus($node, NodeStatus::Active);
    }

    public function disableNode(Node $node): Node
    {
        return $this->setStatus($node, NodeStatus::Disabled);
    }

    private function setStatus(Node $node, NodeStatus $status): Node
    {
        $node->update(['status' => $status]);

        return $node->fresh();
    }

    private function isUnderPressure(Node $node): bool
    {
        $threshold = (float) config('nodes.pressure_threshold_percent');
        $memoryPercent = $node->memory_usage_mb !== null && $node->memory_total_mb > 0
            ? $node->memory_usage_mb / $node->memory_total_mb * 100
            : 0;
        $diskPercent = $node->disk_usage_mb !== null && $node->disk_total_mb > 0
            ? $node->disk_usage_mb / $node->disk_total_mb * 100
            : 0;

        return ($node->cpu_usage_percent ?? 0) > $threshold || $memoryPercent > $threshold || $diskPercent > $threshold;
    }
}
