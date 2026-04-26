<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Throttle: max attempts per email+IP key before lockout.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Throttle: decay window in seconds. Counter resets to 0 after this many
     * seconds with no attempts. Also the lockout duration once threshold hits.
     */
    private const DECAY_SECONDS = 60;

    public function showLogin(): View
    {
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        // Single ambiguous message for every failure mode — never leak which
        // field tripped, whether email exists, or whether format is valid.
        $errorMessage = 'Incorrect email or password.';

        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ], [
            'email.required'    => $errorMessage,
            'email.email'       => $errorMessage,
            'password.required' => $errorMessage,
            'password.string'   => $errorMessage,
        ]);

        $throttleKey = Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        if (! Auth::guard('admin')->attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            return back()
                ->withInput($request->only('email'))
                ->with('login_error', $errorMessage);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
