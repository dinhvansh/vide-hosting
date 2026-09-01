<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Services\AuditService;
use App\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function __construct(private AuthTokenService $tokens, private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->apiTokens()->latest()->get()->map(fn (ApiToken $token): array => [
            'id' => $token->id, 'name' => $token->name, 'actor_type' => $token->actor_type,
            'abilities' => $token->abilities, 'last_used_at' => $token->last_used_at,
            'expires_at' => $token->expires_at, 'revoked_at' => $token->revoked_at,
        ]);

        return response()->json(['data' => $tokens, 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function createMcp(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $token = $this->tokens->create($request->user(), $data['name'], 'MCP', ['projects:read', 'projects:create', 'deployments:read', 'deployments:create', 'apps:operate', 'env:read', 'env:write', 'usage:read'], (int) config('services.mcp_token_ttl_minutes'));
        $this->audit->record($request, $request->user(), 'token.mcp_created', 'api_token', $token['access_token']->id, ['name' => $data['name']]);

        return response()->json(['data' => ['id' => $token['access_token']->id, 'token' => $token['plain_text_token'], 'expires_at' => $token['access_token']->expires_at], 'meta' => ['warning' => 'This token is shown only once.'], 'request_id' => $request->attributes->get('request_id')], 201);
    }

    public function destroy(Request $request, ApiToken $token): JsonResponse
    {
        abort_unless($token->user_id === $request->user()->id, 404);
        $token->update(['revoked_at' => now()]);
        $this->audit->record($request, $request->user(), 'token.revoked', 'api_token', $token->id);

        return response()->json(['data' => ['revoked' => true], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }
}
