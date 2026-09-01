<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\DeploymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeploymentResource;
use App\Models\Application;
use App\Models\Deployment;
use App\Services\ApplicationLogRedactor;
use App\Services\AuditService;
use App\Services\DeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    public function __construct(private DeploymentService $deployments, private AuditService $audit, private DeploymentProvider $provider, private ApplicationLogRedactor $redactor) {}

    public function index(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);

        return response()->json(['data' => DeploymentResource::collection($app->deployments()->latest()->get()), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function store(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);
        $request->validate(['branch' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\/-]+$/']]);
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey !== null && (! is_string($idempotencyKey) || strlen($idempotencyKey) > 100 || ! preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey))) {
            return response()->json(['error' => ['code' => 'INVALID_IDEMPOTENCY_KEY', 'message' => 'Idempotency-Key contains invalid characters or is too long.', 'details' => (object) []], 'request_id' => $request->attributes->get('request_id')], 422);
        }
        $result = $this->deployments->create($app, $request->user(), $request->input('branch'), $idempotencyKey);
        $deployment = $result['deployment'];
        if ($result['created']) {
            $this->audit->record($request, $request->user(), 'deployment.created', 'deployment', $deployment->id);
        }

        return response()->json(['data' => new DeploymentResource($deployment), 'meta' => ['idempotent_replay' => ! $result['created']], 'request_id' => $request->attributes->get('request_id')], 202);
    }

    public function show(Request $request, Application $app, Deployment $deployment): JsonResponse
    {
        $this->assertOwner($request, $app);
        abort_unless($deployment->application_id === $app->id, 404);

        return response()->json(['data' => new DeploymentResource($deployment), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function showDirect(Request $request, Deployment $deployment): JsonResponse
    {
        $this->assertOwner($request, $deployment->application);

        return response()->json(['data' => new DeploymentResource($deployment), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function logs(Request $request, Application $app, Deployment $deployment): JsonResponse
    {
        $this->assertOwner($request, $app);
        abort_unless($deployment->application_id === $app->id, 404);
        $tail = $this->validatedTail($request);

        return response()->json(['data' => ['logs' => $this->tail($this->redactor->redact($app, $deployment->build_logs ?? 'Build has not started.'), $tail)], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function logsDirect(Request $request, Deployment $deployment): JsonResponse
    {
        $this->assertOwner($request, $deployment->application);
        $tail = $this->validatedTail($request);

        return response()->json(['data' => ['logs' => $this->tail($this->redactor->redact($deployment->application, $deployment->build_logs ?? 'Build has not started.'), $tail)], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function runtimeLogs(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);
        $tail = $this->validatedTail($request);

        return response()->json(['data' => ['logs' => $this->redactor->redact($app, $this->provider->runtimeLogs($app, $tail))], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function restart(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);
        $this->provider->restart($app);
        $app->update(['status' => 'RUNNING']);
        $this->audit->record($request, $request->user(), 'application.restarted', 'application', $app->id);

        return response()->json(['data' => ['status' => 'RUNNING'], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], 202);
    }

    public function stop(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);
        $this->provider->stop($app);
        $app->update(['status' => 'STOPPED']);
        $this->audit->record($request, $request->user(), 'application.stopped', 'application', $app->id);

        return response()->json(['data' => ['status' => 'STOPPED'], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], 202);
    }

    private function assertOwner(Request $request, Application $app): void
    {
        abort_unless($request->user()->isAdmin() || $app->user_id === $request->user()->id, 404);
    }

    private function validatedTail(Request $request): int
    {
        $validated = $request->validate(['tail' => ['sometimes', 'integer', 'min:1', 'max:500']]);

        return (int) ($validated['tail'] ?? 200);
    }

    private function tail(string $logs, int $lineCount): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $logs) ?: [];

        return implode("\n", array_slice($lines, -$lineCount));
    }
}
