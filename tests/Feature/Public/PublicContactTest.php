<?php

namespace Tests\Feature\Public;

use App\Mail\ContactFormMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.contact_email' => 'team@rentconnectph.test']);
        Mail::fake();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'    => 'Maria Santos',
            'email'   => 'maria@example.com',
            'message' => 'Hi, I have a property in Lapasan I would like to list. Please call me back.',
        ], $overrides);
    }

    // ---------------------------------------------------------------------
    // Happy path — Mailable + redirect + flash
    // ---------------------------------------------------------------------

    public function test_it_sends_the_mailable_and_redirects_to_contact_sent_with_flash(): void
    {
        $payload = $this->validPayload();

        $response = $this->post('/contact', $payload);

        $response->assertRedirect(route('contact.sent'));
        $response->assertSessionHas('success', "Thanks! We've got your message.");

        Mail::assertSent(ContactFormMessage::class, function ($mail) use ($payload) {
            return $mail->hasTo('team@rentconnectph.test')
                && $mail->hasReplyTo($payload['email'])
                && str_contains($mail->envelope()->subject ?? '', $payload['name'])
                && $mail->name    === $payload['name']
                && $mail->email   === $payload['email']
                && $mail->message === $payload['message'];
        });
    }

    // ---------------------------------------------------------------------
    // Validation — required (3 cases)
    // ---------------------------------------------------------------------

    public function test_it_rejects_missing_name(): void
    {
        $this->post('/contact', $this->validPayload(['name' => '']))
            ->assertSessionHasErrors(['name' => 'Name is required.']);
    }

    public function test_it_rejects_missing_email(): void
    {
        $this->post('/contact', $this->validPayload(['email' => '']))
            ->assertSessionHasErrors(['email' => 'Email is required.']);
    }

    public function test_it_rejects_missing_message(): void
    {
        $this->post('/contact', $this->validPayload(['message' => '']))
            ->assertSessionHasErrors(['message' => 'Message is required.']);
    }

    // ---------------------------------------------------------------------
    // Validation — format
    // ---------------------------------------------------------------------

    public function test_it_rejects_invalid_email_format(): void
    {
        $this->post('/contact', $this->validPayload(['email' => 'not-an-email']))
            ->assertSessionHasErrors(['email' => 'Please enter a valid email address.']);
    }

    // ---------------------------------------------------------------------
    // Validation — length caps (looped). The email oversize value is
    // email-shaped (245 'a's + '@example.com' = 257 chars) so it passes
    // rfc format check and trips email.max not email.email.
    // ---------------------------------------------------------------------

    public function test_it_rejects_oversize_fields(): void
    {
        $cases = [
            'name'    => [str_repeat('a', 121),                  'Name must be 120 characters or fewer.'],
            'email'   => [str_repeat('a', 245) . '@example.com', 'Email must be 255 characters or fewer.'],
            'message' => [str_repeat('a', 5001),                 'Message must be 5000 characters or fewer.'],
        ];

        foreach ($cases as $field => [$value, $message]) {
            $this->post('/contact', $this->validPayload([$field => $value]))
                ->assertSessionHasErrors([$field => $message]);
        }
    }

    // ---------------------------------------------------------------------
    // Throttle — `throttle:5,1` ip-keyed on POST. Laravel's array cache
    // driver in tests auto-resets per test method.
    // ---------------------------------------------------------------------

    public function test_it_throttles_after_5_requests_in_a_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $this->validPayload())
                ->assertRedirect(route('contact.sent'));
        }

        $this->post('/contact', $this->validPayload())->assertStatus(429);
    }
}
