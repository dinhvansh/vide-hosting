<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnvironmentVariableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['key' => $this->key, 'is_secret' => $this->is_secret, 'has_value' => true, 'updated_at' => $this->updated_at];
    }
}
