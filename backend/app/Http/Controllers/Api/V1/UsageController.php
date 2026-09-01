<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\DeploymentProvider;
use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function __construct(private DeploymentProvider $provider) {}

    public function show(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);
        $usage = $this->provider->usage($app);

        return response()->json(['data' => [...$usage, 'limits' => ['cpu' => (float) $app->cpu_limit, 'memory_mb' => $app->memory_limit_mb, 'disk_mb' => $app->disk_limit_mb]], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function index(Request $request): JsonResponse
    {
        $applications = $request->user()->applications()->get();
        $items = $applications->map(fn (Application $app): array => ['application_id' => $app->id, 'name' => $app->name, ...$this->provider->usage($app)]);

        return response()->json(['data' => ['applications' => $items, 'totals' => ['cpu' => $items->sum('cpu'), 'memory_mb' => $items->sum('memory_mb'), 'disk_mb' => $items->sum('disk_mb')]], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    private function assertOwner(Request $request, Application $app): void
    {
        abort_unless($request->user()->isAdmin() || $app->user_id === $request->user()->id, 404);
    }
}
