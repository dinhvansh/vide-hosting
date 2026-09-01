<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'email_verified_at' => $this->email_verified_at, 'role' => $this->role, 'status' => $this->status, 'applications_count' => $this->whenCounted('applications'), 'applications_memory_limit_mb' => $this->when(isset($this->applications_sum_memory_limit_mb), (int) $this->applications_sum_memory_limit_mb), 'quota' => $this->whenLoaded('quota'), 'created_at' => $this->created_at];
    }
}
