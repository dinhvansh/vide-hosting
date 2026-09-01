<?php

namespace App\Http\Middleware;

use App\Exceptions\PlatformException;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\AuditService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditMcpRequest
{
    public function __construct(private AuditService $audit) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $request->attributes->get('access_token');
        if (! $accessToken instanceof ApiToken || $accessToken->actor_type !== 'MCP') {
            return $next($request);
        }

        try {
            $response = $next($request);
            $this->record($request, $response->getStatusCode());

            return $response;
        } catch (Throwable $exception) {
            $this->record($request, $this->statusCode($exception));

            throw $exception;
        }
    }

    private function record(Request $request, int $statusCode): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return;
        }

        $this->audit->record(
            $request,
            $user,
            'mcp.tool_called',
            'mcp_tool',
            $this->resourceId($request),
            [
                'tool' => $this->toolName($request),
                'method' => $request->method(),
                'inputs' => $request->all(),
                'outcome' => $statusCode < 400 ? 'succeeded' : 'failed',
                'status_code' => $statusCode,
            ],
        );
    }

    private function toolName(Request $request): string
    {
        $route = $request->route();
        $signature = $request->method().' '.($route?->uri() ?? 'unknown');

        return match ($signature) {
            'GET api/v1/apps' => 'projects.list',
            'GET api/v1/apps/{app}' => 'projects.get',
            'POST api/v1/apps' => 'projects.create',
            'POST api/v1/apps/{app}/deployments' => 'deployments.create',
            'GET api/v1/deployments/{deployment}',
            'GET api/v1/apps/{app}/deployments/{deployment}' => 'deployments.status',
            'GET api/v1/deployments/{deployment}/logs',
            'GET api/v1/apps/{app}/deployments/{deployment}/logs' => 'deployments.logs',
            'POST api/v1/apps/{app}/restart' => 'apps.restart',
            'POST api/v1/apps/{app}/stop' => 'apps.stop',
            'GET api/v1/apps/{app}/env' => 'env.list',
            'POST api/v1/apps/{app}/env',
            'PATCH api/v1/apps/{app}/env/{key}' => 'env.set',
            'GET api/v1/apps/{app}/usage' => 'usage.get',
            default => 'api.request',
        };
    }

    private function resourceId(Request $request): ?string
    {
        foreach (['deployment', 'app', 'database', 'domain'] as $parameter) {
            $resource = $request->route($parameter);
            if ($resource instanceof Model) {
                return (string) $resource->getKey();
            }

            if (is_string($resource) && $resource !== '') {
                return $resource;
            }
        }

        return null;
    }

    private function statusCode(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof PlatformException => $exception->httpStatus,
            $exception instanceof ValidationException => 422,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }
}
