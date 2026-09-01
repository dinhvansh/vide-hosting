<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\DeploymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\DeploymentResource;
use App\Http\Resources\UserResource;
use App\Models\Application;
use App\Models\Deployment;
use App\Models\User;
use App\Services\AdminOverviewService;
use App\Services\ApplicationService;
use App\Services\AuditService;
use App\Services\DeploymentService;
use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(private AuditService $audit, private QuotaService $quotas, private DeploymentService $deployments, private DeploymentProvider $provider, private AdminOverviewService $overview, private ApplicationService $applications) {}

    public function overview(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->overview->get(), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function users(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:ACTIVE,SUSPENDED,BETA,DISABLED'], 'role' => ['nullable', 'in:SUPER_ADMIN,ADMIN,USER'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $users = User::query()->withCount('applications')->withSum('applications', 'memory_limit_mb')->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->when($data['role'] ?? null, fn ($query, $role) => $query->where('role', $role))->latest()->paginate($data['per_page'] ?? 25);

        return response()->json(['data' => UserResource::collection($users->items()), 'meta' => ['total' => $users->total(), 'page' => $users->currentPage(), 'last_page' => $users->lastPage()], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function user(Request $request, User $user): JsonResponse
    {
        $user->load(['quota', 'applications.domains'])->loadCount('applications');

        return response()->json(['data' => ['user' => new UserResource($user), 'applications' => ApplicationResource::collection($user->applications)], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function apps(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:CREATED,RUNNING,STOPPED,FAILED,SUSPENDED'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $apps = Application::with(['user', 'domains', 'deployments' => fn ($query) => $query->latest()->limit(1)])->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$search.'%')->orWhere('repository_url', 'like', '%'.$search.'%')))->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->latest()->paginate($data['per_page'] ?? 25);

        return response()->json(['data' => ApplicationResource::collection($apps->items()), 'meta' => ['total' => $apps->total(), 'page' => $apps->currentPage(), 'last_page' => $apps->lastPage()], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function app(Request $request, Application $app): JsonResponse
    {
        return response()->json(['data' => new ApplicationResource($app->load(['user', 'domains', 'deployments' => fn ($query) => $query->latest()])), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function buildQueue(Request $request): JsonResponse
    {
        $items = Deployment::with('application.user')->whereIn('status', ['QUEUED', 'BUILDING', 'DEPLOYING'])->oldest()->limit(100)->get()->map(fn (Deployment $deployment): array => ['id' => $deployment->id, 'status' => $deployment->status, 'created_at' => $deployment->created_at, 'application' => ['id' => $deployment->application->id, 'name' => $deployment->application->name, 'owner' => $deployment->application->user->email]]);

        return response()->json(['data' => $items, 'meta' => ['total' => $items->count()], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function restartApp(Request $request, Application $app): JsonResponse
    {
        $this->provider->restart($app);
        $app->update(['status' => 'RUNNING']);
        $this->audit->record($request, $request->user(), 'admin.application_restarted', 'application', $app->id, [], 'ADMIN');

        return response()->json(['data' => ['accepted' => true], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], 202);
    }

    public function stopApp(Request $request, Application $app): JsonResponse
    {
        $this->provider->stop($app);
        $app->update(['status' => 'STOPPED']);
        $this->audit->record($request, $request->user(), 'admin.application_stopped', 'application', $app->id, [], 'ADMIN');

        return response()->json(['data' => ['accepted' => true], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], 202);
    }

    public function redeployApp(Request $request, Application $app): JsonResponse
    {
        $result = $this->deployments->create($app, $request->user(), idempotencyKey: 'admin-'.$request->attributes->get('request_id'));
        $this->audit->record($request, $request->user(), 'admin.application_redeployed', 'deployment', $result['deployment']->id, [], 'ADMIN');

        return response()->json(['data' => new DeploymentResource($result['deployment']), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], 202);
    }

    public function deleteApp(Request $request, Application $app): JsonResponse
    {
        $this->applications->delete($app);
        $this->audit->record($request, $request->user(), 'admin.application_deleted', 'application', $app->id, [], 'ADMIN');

        return response()->json(['data' => ['deleted' => true], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        $user->update(['status' => 'SUSPENDED']);
        $this->audit->record($request, $request->user(), 'admin.user_suspended', 'user', $user->id, [], 'ADMIN');

        return response()->json(['data' => new UserResource($user), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        $user->update(['status' => 'ACTIVE']);
        $this->audit->record($request, $request->user(), 'admin.user_activated', 'user', $user->id, [], 'ADMIN');

        return response()->json(['data' => new UserResource($user), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function quota(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['max_apps' => ['sometimes', 'integer', 'min:1', 'max:20'], 'max_memory_mb_per_app' => ['sometimes', 'integer', 'min:128', 'max:8192'], 'max_cpu_per_app' => ['sometimes', 'numeric', 'min:0.1', 'max:8'], 'max_disk_mb_per_app' => ['sometimes', 'integer', 'min:512', 'max:51200'], 'max_build_concurrency' => ['sometimes', 'integer', 'min:1', 'max:5']]);
        $quota = $this->quotas->for($user);
        $quota->update($data);
        $this->audit->record($request, $request->user(), 'admin.quota_updated', 'user', $user->id, $data, 'ADMIN');

        return response()->json(['data' => $quota, 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }
}
