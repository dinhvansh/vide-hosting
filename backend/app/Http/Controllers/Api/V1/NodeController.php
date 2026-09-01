<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreNodeRequest;
use App\Http\Requests\Api\UpdateNodeRequest;
use App\Http\Resources\NodeResource;
use App\Models\Node;
use App\Services\AuditService;
use App\Services\NodeScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NodeController extends Controller
{
    public function __construct(private AuditService $audit, private NodeScheduler $scheduler) {}

    public function index(Request $request): JsonResponse
    {
        $nodes = Node::query()->withCount('applications')->orderBy('name')->get();

        return response()->json(['data' => NodeResource::collection($nodes), 'meta' => ['total' => $nodes->count()], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function show(Request $request, Node $node): JsonResponse
    {
        return $this->response($request, $node->loadCount('applications'));
    }

    public function store(StoreNodeRequest $request): JsonResponse
    {
        $node = Node::create([...$request->validated(), 'status' => 'ACTIVE']);
        $this->audit->record($request, $request->user(), 'NODE_CREATE', 'node', $node->id, ['code' => $node->code], 'ADMIN');

        return $this->response($request, $node->loadCount('applications'), 201);
    }

    public function update(UpdateNodeRequest $request, Node $node): JsonResponse
    {
        $data = $request->validated();
        $this->assertCapacityNotBelowReservations($node, $data);
        $node->update($data);
        $this->audit->record($request, $request->user(), 'NODE_UPDATE', 'node', $node->id, ['changed_fields' => array_keys($data)], 'ADMIN');

        return $this->response($request, $node->fresh()->loadCount('applications'));
    }

    public function drain(Request $request, Node $node): JsonResponse
    {
        return $this->transition($request, $this->scheduler->markNodeDraining($node), 'NODE_DRAIN');
    }

    public function activate(Request $request, Node $node): JsonResponse
    {
        return $this->transition($request, $this->scheduler->activateNode($node), 'NODE_ACTIVATE');
    }

    public function maintenance(Request $request, Node $node): JsonResponse
    {
        return $this->transition($request, $this->scheduler->markNodeMaintenance($node), 'NODE_MAINTENANCE');
    }

    public function disable(Request $request, Node $node): JsonResponse
    {
        return $this->transition($request, $this->scheduler->disableNode($node), 'NODE_DISABLE');
    }

    /** @param array<string, mixed> $data */
    private function assertCapacityNotBelowReservations(Node $node, array $data): void
    {
        $errors = [];
        if (($data['cpu_total'] ?? $node->cpu_total) < $node->cpu_reserved) {
            $errors['cpu_total'][] = 'CPU capacity cannot be lower than reserved CPU.';
        }
        if (($data['memory_total_mb'] ?? $node->memory_total_mb) < $node->memory_reserved_mb) {
            $errors['memory_total_mb'][] = 'Memory capacity cannot be lower than reserved memory.';
        }
        if (($data['disk_total_mb'] ?? $node->disk_total_mb) < $node->disk_reserved_mb) {
            $errors['disk_total_mb'][] = 'Disk capacity cannot be lower than reserved disk.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function transition(Request $request, Node $node, string $action): JsonResponse
    {
        $this->audit->record($request, $request->user(), $action, 'node', $node->id, ['status' => $node->status->value], 'ADMIN');

        return $this->response($request, $node->loadCount('applications'));
    }

    private function response(Request $request, Node $node, int $status = 200): JsonResponse
    {
        return response()->json(['data' => new NodeResource($node), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], $status);
    }
}
