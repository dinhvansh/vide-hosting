<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink(['email' => $data['email']]);

        return response()->json(['data' => ['message' => 'If an account exists, a password reset email has been queued.'], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], 202);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $user->apiTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['error' => ['code' => 'INVALID_RESET_TOKEN', 'message' => 'The password reset link is invalid or expired.', 'details' => (object) []], 'request_id' => $request->attributes->get('request_id')], 422);
        }

        return response()->json(['data' => ['reset' => true], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }
}
