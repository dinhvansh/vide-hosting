<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private AuthTokenService $tokens, private SubscriptionService $subscriptions) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([...$request->safe()->except('password_confirmation'), 'status' => 'BETA', 'role' => 'USER']);
        $this->subscriptions->for($user);
        $token = $this->tokens->create($user, $request->userAgent() ?: 'Browser');
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json(['data' => ['user' => new UserResource($user), 'token' => $token['plain_text_token'], 'expires_at' => $token['access_token']->expires_at], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();
        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return response()->json(['error' => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Email or password is incorrect.', 'details' => (object) []], 'request_id' => $request->attributes->get('request_id')], 422);
        }
        $token = $this->tokens->create($user, $request->userAgent() ?: 'Browser');

        return response()->json(['data' => ['user' => new UserResource($user), 'token' => $token['plain_text_token'], 'expires_at' => $token['access_token']->expires_at], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('access_token')->update(['revoked_at' => now()]);

        return response()->json(['data' => ['logged_out' => true], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }

    public function me(Request $request): JsonResponse
    {
        $this->subscriptions->for($request->user());

        return response()->json(['data' => new UserResource($request->user()->load(['subscription.plan', 'quota'])), 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }
}
