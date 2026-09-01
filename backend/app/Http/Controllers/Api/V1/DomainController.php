<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDomainRequest;
use App\Http\Resources\DomainResource;
use App\Models\Application;
use App\Models\Domain;
use App\Services\AuditService;
use App\Services\DomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function __construct(private DomainService $domains, private AuditService $audit) {}

    public function index(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);

        return response()->json(['data' => DomainResource::collection($app->domains()->get()), 'meta' => ['custom_domains_enabled' => (bool) config('services.custom_domains_enabled')], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function store(StoreDomainRequest $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);
        $domain = $this->domains->createCustom($app, $request->string('domain'));
        $this->audit->record($request, $request->user(), 'domain.created', 'domain', $domain->id, ['domain' => $domain->domain]);

        return response()->json(['data' => new DomainResource($domain), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], 201);
    }

    public function destroy(Request $request, Application $app, Domain $domain): JsonResponse
    {
        $this->assertOwner($request, $app);
        abort_unless($domain->application_id === $app->id, 404);
        $this->domains->delete($app, $domain);
        $this->audit->record($request, $request->user(), 'domain.deleted', 'domain', $domain->id, ['domain' => $domain->domain]);

        return response()->json(['data' => ['deleted' => true], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    private function assertOwner(Request $request, Application $app): void
    {
        abort_unless($request->user()->isAdmin() || $app->user_id === $request->user()->id, 404);
    }
}
