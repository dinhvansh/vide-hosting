<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'repository_url' => $this->repository_url, 'branch' => $this->branch,
            'framework' => $this->framework, 'status' => $this->status,
            'owner' => new UserResource($this->whenLoaded('user')),
            'resources' => ['cpu' => (float) $this->cpu_limit, 'memory_mb' => $this->memory_limit_mb, 'disk_mb' => $this->disk_limit_mb],
            'domain' => $this->whenLoaded('domains', fn () => $this->domains->first()?->domain),
            'latest_deployment' => new DeploymentResource($this->whenLoaded('deployments', fn () => $this->deployments->first())),
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
