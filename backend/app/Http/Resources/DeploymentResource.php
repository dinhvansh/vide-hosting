<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeploymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'application_id' => $this->application_id, 'status' => $this->status,
            'branch' => $this->branch, 'commit_sha' => $this->commit_sha,
            'build_started_at' => $this->build_started_at, 'deploy_started_at' => $this->deploy_started_at,
            'finished_at' => $this->finished_at, 'error' => $this->error_code ? ['code' => $this->error_code, 'message' => $this->error_message] : null,
            'created_at' => $this->created_at,
        ];
    }
}
