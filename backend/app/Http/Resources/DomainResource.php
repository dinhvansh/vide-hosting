<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DomainResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'domain' => $this->domain, 'type' => $this->type, 'status' => $this->status, 'ssl_status' => $this->ssl_status, 'created_at' => $this->created_at];
    }
}
