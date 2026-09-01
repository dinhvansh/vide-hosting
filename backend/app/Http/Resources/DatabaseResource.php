<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatabaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'database_name' => $this->database_name, 'database_user' => $this->database_user, 'host' => $this->host, 'port' => $this->port, 'status' => $this->status, 'has_password' => true, 'created_at' => $this->created_at];
    }
}
