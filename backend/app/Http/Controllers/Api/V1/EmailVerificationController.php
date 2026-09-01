<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->json(['data' => ['queued' => true, 'already_verified' => $request->user()->hasVerifiedEmail()], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')], 202);
    }

    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403);
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['data' => ['verified' => true], 'meta' => (object) [], 'request_id' => $request->attributes->get('request_id')]);
    }
}
