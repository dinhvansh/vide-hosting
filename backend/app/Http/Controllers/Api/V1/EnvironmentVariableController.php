<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\DeploymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEnvironmentVariableRequest;
use App\Http\Resources\EnvironmentVariableResource;
use App\Models\Application;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnvironmentVariableController extends Controller
{
    public function __construct(private AuditService $audit, private DeploymentProvider $provider) {}

    public function index(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);

        return response()->json(['data' => EnvironmentVariableResource::collection($app->environmentVariables()->orderBy('key')->get()), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function store(StoreEnvironmentVariableRequest $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);
        $variables = $app->environmentVariables()->get()->mapWithKeys(fn ($variable): array => [$variable->key => $variable->encrypted_value])->all();
        $variables[$request->string('key')->toString()] = $request->string('value')->toString();
        $this->provider->setEnvironmentVariables($app, $variables);
        $variable = $app->environmentVariables()->updateOrCreate(['key' => $request->string('key')], ['encrypted_value' => $request->string('value'), 'is_secret' => $request->boolean('is_secret', true)]);
        $this->audit->record($request, $request->user(), 'environment.updated', 'application', $app->id, ['key' => $variable->key, 'value' => $request->input('value')]);

        return response()->json(['data' => new EnvironmentVariableResource($variable), 'meta' => ['redeploy_recommended' => true], 'request_id' => $request->attributes->get('request_id')], 201);
    }

    public function destroy(Request $request, Application $app, string $key): JsonResponse
    {
        $this->assertOwner($request, $app);
        $variable = $app->environmentVariables()->where('key', $key)->firstOrFail();
        $variables = $app->environmentVariables()->whereKeyNot($variable->id)->get()->mapWithKeys(fn ($item): array => [$item->key => $item->encrypted_value])->all();
        $this->provider->setEnvironmentVariables($app, $variables);
        $variable->delete();
        $this->audit->record($request, $request->user(), 'environment.deleted', 'application', $app->id, ['key' => $key]);

        return response()->json(['data' => ['deleted' => true], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    private function assertOwner(Request $request, Application $app): void
    {
        abort_unless($request->user()->isAdmin() || $app->user_id === $request->user()->id, 404);
    }
}
