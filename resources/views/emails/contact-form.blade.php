<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; line-height: 1.5; color: #1f2937;">
    <h2 style="margin: 0 0 16px;">New contact message</h2>

    <p style="margin: 0 0 8px;"><strong>From:</strong> {{ $name }}</p>
    <p style="margin: 0 0 8px;"><strong>Email:</strong> <a href="mailto:{{ $email }}">{{ $email }}</a></p>

    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 16px 0;">

    <p style="margin: 0 0 8px;"><strong>Message:</strong></p>
    <p style="white-space: pre-wrap; margin: 0;">{{ $body }}</p>

    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 16px 0;">

    <p style="font-size: 12px; color: #6b7280; margin: 0;">
        Sent via the contact form on rentconnectph.com.
        Hit Reply to respond directly to {{ $name }}.
    </p>
</body>
</html>
