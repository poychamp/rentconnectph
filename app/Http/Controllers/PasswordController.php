<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Single ambiguous user-facing message reused across all forgot-password
     * outcomes. Dot-suffix is a dev-only signature so the path that fired is
     * recognizable during smoke (1=known-success, 2=required, 3=format,
     * 4=unknown-success / no user / throttled).
     */
    private const AMBIGUOUS_MESSAGE = "If an account with that email exists, we've sent a reset link.";

    /**
     * Collapsed failure message for the reset endpoint. Covers invalid token,
     * expired token, email-token mismatch, and missing user — never reveals
     * which specific failure mode tripped.
     */
    private const GENERIC_FAILURE = 'This password reset link is invalid or has expired. Please request a new one.';

    public function request(): View
    {
        return view('password.request');
    }

    public function reset(string $token, Request $request): View
    {
        return view('password.reset', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email:rfc'],
        ], [
            'email.required' => self::AMBIGUOUS_MESSAGE.'.',
            'email.email'    => self::AMBIGUOUS_MESSAGE.'..',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        $message = $status === Password::RESET_LINK_SENT
            ? self::AMBIGUOUS_MESSAGE
            : self::AMBIGUOUS_MESSAGE.'...';

        return back()->with('success', $message);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email:rfc'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'token.required'     => self::GENERIC_FAILURE,
            'email.required'     => 'Please enter a valid email address.',
            'email.email'        => 'Please enter a valid email address.',
            'password.required'  => 'Please choose a password.',
            'password.min'       => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $request->session()->regenerate();

            return redirect()
                ->route('auth.login')
                ->with('success', 'Password updated. Sign in.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => self::GENERIC_FAILURE]);
    }
}
