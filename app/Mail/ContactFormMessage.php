<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      [config('mail.contact_email')],
            replyTo: [new Address($this->email, $this->name)],
            subject: '[RentConnectPH ' . ucfirst(config('app.env')) . '] New contact message from ' . $this->name,
        );
    }

    public function content(): Content
    {
        // `$message` collides with Laravel's auto-injected Illuminate\Mail\Message
        // instance in the view. Rename to `$body` so the view renders the user's
        // text, not the message-builder object.
        return new Content(
            view: 'emails.contact-form',
            with: ['body' => $this->message],
        );
    }
}
