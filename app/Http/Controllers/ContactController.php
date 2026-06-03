<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('contact');
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'email'   => ['required', 'email:rfc', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ], [
            'name.required'    => 'Name is required.',
            'name.max'         => 'Name must be 120 characters or fewer.',
            'email.required'   => 'Email is required.',
            'email.email'      => 'Please enter a valid email address.',
            'email.max'        => 'Email must be 255 characters or fewer.',
            'message.required' => 'Message is required.',
            'message.max'      => 'Message must be 5000 characters or fewer.',
        ]);

        Mail::to(config('mail.contact_email'))->send(new ContactFormMessage(
            name:    $validated['name'],
            email:   $validated['email'],
            message: $validated['message'],
        ));

        return redirect()
            ->route('contact.sent')
            ->with('success', "Thanks! We've got your message.");
    }

    public function sent(): View
    {
        return view('contact.sent');
    }
}
