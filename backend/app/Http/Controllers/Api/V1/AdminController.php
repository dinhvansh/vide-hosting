<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\DeploymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\DeploymentResource;
use App\Http\Resources\UserResource;
use App\Models\Application;
use App\Models\Deployment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AdminOverviewService;
use App\Services\ApplicationService;
use App\Services\AuditService;
use App\Services\DeploymentService;
use App\Services\QuotaService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(private AuditService $audit, private QuotaService $quotas, private SubscriptionService $subscriptions, private DeploymentService $deployments, private DeploymentProvider $provider, private AdminOverviewService $overview, private ApplicationService $applications) {}

    public function overview(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->overview->get(), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function users(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:ACTIVE,SUSPENDED,BETA,DISABLED'], 'role' => ['nullable', 'in:SUPER_ADMIN,ADMIN,USER'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $users = User::query()->with('subscription.plan')->withCount('applications')->withSum('applications', 'memory_limit_mb')->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->when($data['role'] ?? null, fn ($query, $role) => $query->where('role', $role))->latest()->paginate($data['per_page'] ?? 25);

        return response()->json(['data' => UserResource::collection($users->items()), 'meta' => ['total' => $users->total(), 'page' => $users->currentPage(), 'last_page' => $users->lastPage()], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function user(Request $request, User $user): JsonResponse
    {
        $this->quotas->for($user);
        $this->subscriptions->for($user);
        $user->load(['quota', 'subscription.plan', 'applications.domains', 'applications.node'])->loadCount('applications');

        return response()->json(['data' => ['user' => new UserResource($user), 'applications' => ApplicationResource::collection($user->applications)], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function apps(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:CREATED,RUNNING,STOPPED,FAILED,SUSPENDED'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $apps = Application::with(['user', 'node', 'domains', 'deployments' => fn ($query) => $query->latest()->limit(1)])
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('repository_url', 'like', '%'.$search.'%')
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                ->orWhereHas('node', fn ($node) => $node->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'))))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($data['per_page'] ?? 25);

        return response()->json(['data' => ApplicationResource::collection($apps->items()), 'meta' => ['total' => $apps->total(), 'page' => $apps->currentPage(), 'last_page' => $apps->lastPage()], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function app(Request $request, Application $app): JsonResponse
    {
        return response()->json(['data' => new ApplicationResource($app->load(['user', 'node', 'domains', 'deployments' => fn ($query) => $query->latest()])), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function buildQueue(Request $request): JsonResponse
    {
        $items = Deployment::with('application.user')->whereIn('status', ['QUEUED', 'BUILDING', 'DEPLOYING'])->oldest()->limit(100)->get()->map(fn (Deployment $deployment): array => ['id' => $deployment->id, 'status' => $deployment->status, 'created_at' => $deployment->created_at, 'application' => ['id' => $deployment->application->id, 'name' => $deployment->application->name, 'owner' => $deployment->application->user->email]]);

        return response()->json(['data' => $items, 'meta' => ['total' => $items->count()], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function plans(Request $request): JsonResponse
    {
        $plans = Plan::query()->orderBy('monthly_price_vnd')->get();

        return response()->json(['data' => $plans, 'meta' => ['pricing_status' => 'DRAFT', 'payment_enabled' => false], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function createPlan(Request $request): JsonResponse
    {
        $data = $this->validatePlan($request);
        $plan = DB::transaction(function () use ($data): Plan {
            if ($data['is_default']) {
                Plan::query()->update(['is_default' => false]);
            }

            return Plan::query()->create($data);
        });
        $this->audit->record($request, $request->user(), 'admin.plan_created', 'plan', $plan->id, $data, 'ADMIN');

        return response()->json(['data' => $plan, 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], 201);
    }

    public function updatePlan(Request $request, Plan $plan): JsonResponse
    {
        $data = $this->validatePlan($request, $plan);
        DB::transaction(function () use ($data, $plan): void {
            if ($data['is_default']) {
                Plan::query()->where('id', '!=', $plan->id)->update(['is_default' => false]);
            }
            $plan->update($data);
            $plan->subscriptions()->with('user.quota')->each(function (Subscription $subscription) use ($plan): void {
                $subscription->user->quota()->updateOrCreate([], [
                    'max_apps' => $plan->max_apps + $subscription->extra_app_slots,
                    'max_memory_mb_per_app' => $plan->max_memory_mb_per_app,
                    'max_cpu_per_app' => $plan->max_cpu_per_app,
                    'max_disk_mb_per_app' => $plan->max_disk_mb_per_app,
                    'max_build_concurrency' => $plan->max_build_concurrency,
                ]);
            });
        });
        $this->audit->record($request, $request->user(), 'admin.plan_updated', 'plan', $plan->id, $data, 'ADMIN');

        return response()->json(['data' => $plan->refresh(), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
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

    public function subscription(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'status' => ['required', 'in:TRIALING,ACTIVE,PAST_DUE,EXPIRED,CANCELED'],
            'duration_months' => ['required', 'integer', 'in:1,3,6,12'],
        ]);
        $startsAt = now();
        $endsAt = $startsAt->copy()->addMonthsNoOverflow($data['duration_months']);
        $subscription = $this->subscriptions->for($user);
        $subscription->update([
            'plan_id' => $data['plan_id'],
            'status' => $data['status'],
            'billing_cycle' => 'MONTHLY',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'grace_ends_at' => $endsAt->copy()->addDays(3),
            'reminded_7d_at' => null,
            'reminded_3d_at' => null,
            'reminded_1d_at' => null,
            'expired_notified_at' => null,
        ]);
        $plan = Plan::query()->findOrFail($data['plan_id']);
        $this->quotas->for($user)->update([
            'max_apps' => $plan->max_apps + $subscription->extra_app_slots,
            'max_memory_mb_per_app' => $plan->max_memory_mb_per_app,
            'max_cpu_per_app' => $plan->max_cpu_per_app,
            'max_disk_mb_per_app' => $plan->max_disk_mb_per_app,
            'max_build_concurrency' => $plan->max_build_concurrency,
        ]);
        $this->audit->record($request, $request->user(), 'admin.subscription_updated', 'user', $user->id, [
            'plan_id' => $data['plan_id'],
            'status' => $data['status'],
            'duration_months' => $data['duration_months'],
        ], 'ADMIN');

        return response()->json(['data' => new UserResource($user->fresh()->load('subscription.plan')), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    /** @return array<string, mixed> */
    private function validatePlan(Request $request, ?Plan $plan = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::unique('plans', 'code')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:100'],
            'monthly_price_vnd' => ['required', 'integer', 'min:0', 'max:100000000'],
            'max_apps' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_memory_mb_per_app' => ['required', 'integer', 'min:128', 'max:131072'],
            'max_cpu_per_app' => ['required', 'numeric', 'min:0.1', 'max:64'],
            'max_disk_mb_per_app' => ['required', 'integer', 'min:512', 'max:1048576'],
            'max_build_concurrency' => ['required', 'integer', 'min:1', 'max:100'],
            'is_default' => ['required', 'boolean'],
            'is_published' => ['required', 'boolean'],
        ]);
        if ($data['is_default'] && ! $data['is_published']) {
            throw ValidationException::withMessages(['is_published' => ['The default plan must be published.']]);
        }

        return $data;
    }
}
