<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Enums\AppPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfilePasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionTo(AppPermission::listingsFieldWork()->value, 'admin'),
            403
        );

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'password.required'         => 'Please enter a new password.',
            'password.min'              => 'New password must be at least 8 characters.',
            'password.confirmed'        => 'New password confirmation does not match.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['success' => true]);
    }
}
