<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
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

    public function request(): View
    {
        return view('password.request');
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
}
