<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\DeploymentProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['data' => ['status' => 'ok'], 'meta' => ['timestamp' => now()->toISOString()]]);
    }

    public function ready(DeploymentProvider $provider): JsonResponse
    {
        $checks = ['database' => false, 'cache' => false, 'provider' => false];
        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (Throwable) {
        }

        try {
            $key = 'readiness:'.Str::uuid();
            Cache::put($key, 'ok', 10);
            $checks['cache'] = Cache::get($key) === 'ok';
            Cache::forget($key);
        } catch (Throwable) {
        }

        $checks['provider'] = $provider->health()['connected'];
        $ready = ! in_array(false, $checks, true);

        return response()->json(['data' => ['status' => $ready ? 'ready' : 'unavailable', 'checks' => $checks], 'meta' => ['timestamp' => now()->toISOString()]], $ready ? 200 : 503);
    }
}
