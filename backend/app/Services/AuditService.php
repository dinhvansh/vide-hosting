<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditService
{
    /** @param array<string, mixed> $metadata */
    public function record(Request $request, User $actor, string $action, string $resourceType, ?string $resourceId, array $metadata = [], string $actorType = 'USER'): AuditLog
    {
        if ($request->attributes->get('access_token')?->actor_type === 'MCP') {
            $actorType = 'MCP';
        }

        return AuditLog::create(['actor_type' => $actorType, 'actor_id' => $actor->id, 'action' => $action, 'resource_type' => $resourceType, 'resource_id' => $resourceId, 'request_id' => $request->attributes->get('request_id'), 'ip_address' => $request->ip(), 'metadata_json' => $this->redact($metadata), 'created_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $values[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace('-', '_', $key));

        return $normalized === 'value'
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'authorization')
            || str_contains($normalized, 'token')
            || preg_match('/(^|_)(api|access|private|public|secret)_?key$/', $normalized) === 1;
    }
}
