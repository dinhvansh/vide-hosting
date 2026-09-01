<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NodeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'provider' => $this->provider,
            'region' => $this->region,
            'status' => $this->status->value,
            'applications_count' => $this->whenCounted('applications'),
            'capacity' => [
                'cpu' => ['total' => $this->cpu_total, 'reserved' => $this->cpu_reserved, 'usage_percent' => $this->cpu_usage_percent],
                'memory_mb' => ['total' => $this->memory_total_mb, 'reserved' => $this->memory_reserved_mb, 'usage' => $this->memory_usage_mb],
                'disk_mb' => ['total' => $this->disk_total_mb, 'reserved' => $this->disk_reserved_mb, 'usage' => $this->disk_usage_mb],
            ],
            'last_heartbeat_at' => $this->last_heartbeat_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
