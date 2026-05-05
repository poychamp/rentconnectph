<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user('admin');
        $isField = $user->hasRole(AppRole::field()->value);

        return view('auth.profile', [
            'name' => $user->name,
            'roleLabel' => $user->getRoleNames()->first() ?? '',
            'dashboardUrl' => $isField ? '/field' : '/admin',
        ]);
    }

    public function updateName(Request $request)
    {
        $validated = $request->validateWithBag('update-name', [
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Please enter your name.',
            'name.max' => 'Name must be 255 characters or fewer.',
        ]);

        $request->user('admin')->update(['name' => $validated['name']]);

        return back()->with('success', 'Name updated.');
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'current_password.current_password' => 'Current password is incorrect.',
            'password.required' => 'Please enter a new password.',
            'password.min' => 'New password must be at least 8 characters.',
            'password.max' => 'New password must be 255 characters or fewer.',
            'password.confirmed' => 'New password confirmation does not match.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'update-password');
        }

        $request->user('admin')->forceFill([
            'password' => Hash::make($request->input('password')),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        return back()->with('success', 'Password updated.');
    }
}
