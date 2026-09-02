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
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'email_verified_at' => $this->email_verified_at, 'role' => $this->role, 'status' => $this->status, 'applications_count' => $this->whenCounted('applications'), 'applications_memory_limit_mb' => $this->when(isset($this->applications_sum_memory_limit_mb), (int) $this->applications_sum_memory_limit_mb), 'quota' => $this->whenLoaded('quota'), 'subscription' => $this->whenLoaded('subscription', fn (): array => [
            'id' => $this->subscription->id,
            'status' => $this->subscription->status,
            'billing_cycle' => $this->subscription->billing_cycle,
            'extra_app_slots' => $this->subscription->extra_app_slots,
            'starts_at' => $this->subscription->starts_at,
            'ends_at' => $this->subscription->ends_at,
            'grace_ends_at' => $this->subscription->grace_ends_at,
            'plan' => [
                'id' => $this->subscription->plan->id,
                'code' => $this->subscription->plan->code,
                'name' => $this->subscription->plan->name,
                'monthly_price_vnd' => $this->subscription->plan->monthly_price_vnd,
                'is_published' => $this->subscription->plan->is_published,
            ],
        ]), 'created_at' => $this->created_at];
    }
}
