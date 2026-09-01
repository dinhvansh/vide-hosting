<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => ['sometimes', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/', Rule::unique('nodes', 'code')->ignore($this->route('node'))],
            'provider' => ['sometimes', 'in:FAKE,DOKPLOY'],
            'provider_server_id' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'cpu_total' => ['sometimes', 'numeric', 'min:0.1', 'max:1024'],
            'memory_total_mb' => ['sometimes', 'integer', 'min:128'],
            'disk_total_mb' => ['sometimes', 'integer', 'min:512'],
            'cpu_usage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'memory_usage_mb' => ['nullable', 'integer', 'min:0'],
            'disk_usage_mb' => ['nullable', 'integer', 'min:0'],
            'last_heartbeat_at' => ['nullable', 'date'],
        ];
    }
}
