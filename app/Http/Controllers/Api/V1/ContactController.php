<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ContactFormMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function __invoke(Request $request): JsonResponse
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

        return response()->json(['success' => true]);
    }
}
