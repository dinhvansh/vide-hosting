<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PlatformException;
use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Services\BillingService;
use App\Services\SePayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(private BillingService $billing, private SePayGateway $gateway) {}

    public function catalog(Request $request): JsonResponse
    {
        $subscription = $request->user()->subscription()->with('plan')->first();

        return response()->json(['data' => [
            'plans' => Plan::query()->where('is_published', true)->where('monthly_price_vnd', '>', 0)->orderBy('monthly_price_vnd')->get()->map(fn (Plan $plan): array => $this->planData($plan))->values(),
            'terms' => [1, 3, 6, 12],
            'app_slot_monthly_price_vnd' => (int) config('services.billing.app_slot_monthly_price_vnd', 49000),
            'extra_app_slots' => $subscription?->extra_app_slots ?? 0,
            'payment_available' => $this->gateway->isConfigured(),
        ]]);
    }

    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['PLAN', 'APP_SLOT'])],
            'plan_id' => ['required_if:type,PLAN', 'nullable', 'uuid', 'exists:plans,id'],
            'duration_months' => ['required_if:type,PLAN', 'nullable', Rule::in([1, 3, 6, 12])],
            'quantity' => ['required_if:type,APP_SLOT', 'nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $result = $validated['type'] === 'PLAN'
            ? $this->billing->createPlanOrder($request->user(), Plan::query()->findOrFail($validated['plan_id']), (int) $validated['duration_months'])
            : $this->billing->createAppSlotOrder($request->user(), (int) $validated['quantity']);

        return response()->json(['data' => ['order' => $this->orderData($result['order']), 'checkout' => $result['checkout']]], 201);
    }

    public function show(Request $request, PaymentOrder $paymentOrder): JsonResponse
    {
        abort_unless($paymentOrder->user_id === $request->user()->id, 404);

        return response()->json(['data' => $this->orderData($paymentOrder)]);
    }

    public function reconcile(Request $request, PaymentOrder $paymentOrder): JsonResponse
    {
        abort_unless($paymentOrder->user_id === $request->user()->id, 404);
        $payload = $this->gateway->query($paymentOrder);
        if ($payload !== null) {
            $paymentOrder = $this->billing->processProviderPayload($payload);
        }

        return response()->json(['data' => $this->orderData($paymentOrder->refresh())]);
    }

    public function ipn(Request $request): JsonResponse
    {
        if (! $this->gateway->validIpnSecret($request->header('x-secret-key'))) {
            throw new PlatformException('INVALID_PAYMENT_SIGNATURE', 'Invalid payment signature.', 401);
        }
        $order = $this->billing->processProviderPayload($request->all());

        return response()->json(['success' => true, 'order_id' => $order->id]);
    }

    /** @return array<string, mixed> */
    private function planData(Plan $plan): array
    {
        return $plan->only(['id', 'code', 'name', 'monthly_price_vnd', 'max_apps', 'max_memory_mb_per_app', 'max_cpu_per_app', 'max_disk_mb_per_app', 'max_build_concurrency']);
    }

    /** @return array<string, mixed> */
    private function orderData(PaymentOrder $order): array
    {
        return $order->only(['id', 'invoice_number', 'type', 'duration_months', 'quantity', 'amount_vnd', 'status', 'approved_at', 'expires_at', 'created_at']);
    }
}
