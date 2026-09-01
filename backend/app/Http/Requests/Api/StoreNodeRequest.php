<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreNodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/', 'unique:nodes,code'],
            'provider' => ['required', 'in:FAKE,DOKPLOY'],
            'provider_server_id' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'cpu_total' => ['required', 'numeric', 'min:0.1', 'max:1024'],
            'memory_total_mb' => ['required', 'integer', 'min:128'],
            'disk_total_mb' => ['required', 'integer', 'min:512'],
        ];
    }
}
