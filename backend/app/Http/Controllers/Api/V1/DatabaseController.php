<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDatabaseRequest;
use App\Http\Resources\DatabaseResource;
use App\Models\Application;
use App\Models\Database;
use App\Services\AuditService;
use App\Services\DatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatabaseController extends Controller
{
    public function __construct(private DatabaseService $databases, private AuditService $audit) {}

    public function index(Request $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);

        return response()->json(['data' => DatabaseResource::collection($app->databases()->get()), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function store(StoreDatabaseRequest $request, Application $app): JsonResponse
    {
        $this->assertOwner($request, $app);
        abort_if($app->databases()->exists(), 409, 'An application can have only one managed database during beta.');
        $result = $this->databases->create($app, $request->string('type'));
        $this->audit->record($request, $request->user(), 'database.created', 'database', $result['database']->id, ['type' => $result['database']->type]);

        return response()->json(['data' => ['database' => new DatabaseResource($result['database']), 'password' => $result['password']], 'meta' => ['warning' => 'The password is shown only once.'], 'request_id' => $request->attributes->get('request_id')], 201);
    }

    public function destroy(Request $request, Application $app, Database $database): JsonResponse
    {
        $this->assertOwner($request, $app);
        abort_unless($database->application_id === $app->id, 404);
        $this->databases->delete($app, $database);
        $this->audit->record($request, $request->user(), 'database.deleted', 'database', $database->id);

        return response()->json(['data' => ['deleted' => true], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    private function assertOwner(Request $request, Application $app): void
    {
        abort_unless($request->user()->isAdmin() || $app->user_id === $request->user()->id, 404);
    }
}
