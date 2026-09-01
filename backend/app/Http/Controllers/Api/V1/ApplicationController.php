<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Services\ApplicationService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(private ApplicationService $applications, private AuditService $audit) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $apps = $request->user()->applications()->with(['domains', 'deployments' => fn ($query) => $query->latest()->limit(1)])->latest()->get();

        return response()->json(['data' => ApplicationResource::collection($apps), 'meta' => ['total' => $apps->count()], 'request_id' => $request->attributes->get('request_id')]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $app = $this->applications->create($request->user(), $request->validated());
        $this->audit->record($request, $request->user(), 'APP_NODE_ASSIGN', 'application', $app->id);
        $this->audit->record($request, $request->user(), 'application.created', 'application', $app->id, ['repository_url' => $app->repository_url]);

        return response()->json(['data' => new ApplicationResource($app), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);

        return response()->json(['data' => new ApplicationResource($app->load(['domains', 'deployments' => fn ($query) => $query->latest()])), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:100'], 'branch' => ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\/-]+$/'], 'framework' => ['sometimes', 'in:auto,nextjs,node,laravel,python,static']]);
        $app = $this->applications->update($app, $data);
        $this->audit->record($request, $request->user(), 'application.updated', 'application', $app->id, ['changed_fields' => array_keys($data)]);

        return response()->json(['data' => new ApplicationResource($app), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);
        $this->applications->delete($app);
        $this->audit->record($request, $request->user(), 'application.deleted', 'application', $app->id);

        return response()->json(['data' => ['deleted' => true], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    private function assertOwner(Request $request, Application $app): void
    {
        abort_unless($request->user()->isAdmin() || $app->user_id === $request->user()->id, 404);
    }
}
