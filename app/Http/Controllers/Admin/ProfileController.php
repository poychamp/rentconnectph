<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
}
