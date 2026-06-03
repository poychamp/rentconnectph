<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AppRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FieldLoginResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FieldLoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    private const AUTH_FAILURE_MESSAGE = "Incorrect email or password, or this account isn't authorized for the field app.";

    public function __invoke(Request $request): JsonResponse
    {
        // Manual Validator + consolidate-on-fail: any rule failure (including
        // missing/malformed password) surfaces under the SAME `email` key.
        // Anti-enumeration — never reveal which field tripped.
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'email' => self::AUTH_FAILURE_MESSAGE,
            ]);
        }

        $throttleKey = Str::lower((string) $request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => self::AUTH_FAILURE_MESSAGE,
            ]);
        }

        if (! $user->hasRole(AppRole::field()->value)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => self::AUTH_FAILURE_MESSAGE,
            ]);
        }

        RateLimiter::clear($throttleKey);

        $deviceName = (string) ($request->header('X-Device-Name') ?: 'field-android');
        $deviceName = mb_substr($deviceName, 0, 255);
        $token      = $user->createToken($deviceName, ['field'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => (new FieldLoginResource($user))->resolve(),
        ], 200);
    }
}
