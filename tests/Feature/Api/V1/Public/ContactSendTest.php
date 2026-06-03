<?php

namespace Tests\Feature\Api\V1\Public;

use App\Mail\ContactFormMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ContactSendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.contact_email' => 'team@rentconnectph.test']);
        RateLimiter::clear('api-contact-send:127.0.0.1');
        RateLimiter::clear('api-inquiry-submit:127.0.0.1');
        Mail::fake();
    }

    private function url(): string
    {
        return route('api.v1.contact');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'    => 'Maria Santos',
            'email'   => 'maria@example.com',
            'message' => 'Hi, I have a property in Lapasan I would like to list. Please call me back.',
        ], $overrides);
    }

    // === ============================================================ ===
    //                              Auth
    // === ============================================================ ===

    public function test_it_allows_guest_with_no_token(): void
    {
        $response = $this->postJson($this->url(), $this->validPayload());

        $response->assertOk();
    }

    // === ============================================================ ===
    //                            Mailable
    // === ============================================================ ===

    public function test_it_sends_the_mailable_to_contact_email_with_envelope_details(): void
    {
        $payload = $this->validPayload();

        $response = $this->postJson($this->url(), $payload);

        $response->assertOk();

        Mail::assertSent(ContactFormMessage::class, function ($mail) use ($payload) {
            return $mail->hasTo('team@rentconnectph.test')
                && $mail->hasReplyTo($payload['email'])
                && str_contains($mail->envelope()->subject ?? '', $payload['name'])
                && $mail->name    === $payload['name']
                && $mail->email   === $payload['email']
                && $mail->message === $payload['message'];
        });
    }

    // === ============================================================ ===
    //                            Validation
    // === ============================================================ ===

    public function test_it_validates_required_name(): void
    {
        $response = $this->postJson($this->url(), $this->validPayload(['name' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'name' => 'Name is required.',
        ]);
        Mail::assertNothingSent();
    }

    public function test_it_validates_name_max_length(): void
    {
        $response = $this->postJson($this->url(), $this->validPayload([
            'name' => str_repeat('a', 121),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'name' => 'Name must be 120 characters or fewer.',
        ]);
        Mail::assertNothingSent();
    }

    public function test_it_validates_required_email(): void
    {
        $response = $this->postJson($this->url(), $this->validPayload(['email' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'email' => 'Email is required.',
        ]);
        Mail::assertNothingSent();
    }

    public function test_it_validates_email_format(): void
    {
        $response = $this->postJson($this->url(), $this->validPayload(['email' => 'not-an-email']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'email' => 'Please enter a valid email address.',
        ]);
        Mail::assertNothingSent();
    }

    public function test_it_validates_email_max_length(): void
    {
        // 245 'a's + '@example.com' = 257 chars. Email-shaped so it passes
        // rfc format and trips email.max not email.email.
        $response = $this->postJson($this->url(), $this->validPayload([
            'email' => str_repeat('a', 245) . '@example.com',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'email' => 'Email must be 255 characters or fewer.',
        ]);
        Mail::assertNothingSent();
    }

    public function test_it_validates_required_message(): void
    {
        $response = $this->postJson($this->url(), $this->validPayload(['message' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'message' => 'Message is required.',
        ]);
        Mail::assertNothingSent();
    }

    public function test_it_validates_message_max_length(): void
    {
        $response = $this->postJson($this->url(), $this->validPayload([
            'message' => str_repeat('a', 5001),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'message' => 'Message must be 5000 characters or fewer.',
        ]);
        Mail::assertNothingSent();
    }

    // === ============================================================ ===
    //                          Response shape
    // === ============================================================ ===

    public function test_it_returns_exact_success_envelope(): void
    {
        $response = $this->postJson($this->url(), $this->validPayload());

        $response->assertOk();
        $response->assertExactJson(['success' => true]);
    }

    // === ============================================================ ===
    //                  Throttle — separate bucket from API inquiry
    // === ============================================================ ===

    public function test_it_throttles_after_five_submissions_in_a_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson($this->url(), $this->validPayload());
            $response->assertOk();
        }

        $sixth = $this->postJson($this->url(), $this->validPayload());
        $sixth->assertStatus(429);
    }

    public function test_its_throttle_is_independent_from_api_inquiry_submit_limiter(): void
    {
        // Each /api/v1/* write endpoint owns its own RateLimiter bucket.
        // Pre-burn the api-inquiry-submit bucket — proves api-contact-send
        // has its own keyspace and isn't accidentally consolidated.
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('api-inquiry-submit:127.0.0.1');
        }

        $this->assertTrue(
            RateLimiter::tooManyAttempts('api-inquiry-submit:127.0.0.1', 5),
            'Sanity check: api-inquiry-submit bucket should be exhausted after 5 hits.',
        );

        $response = $this->postJson($this->url(), $this->validPayload());

        $response->assertOk();
    }

    // === ============================================================ ===
    //                            Middleware
    // === ============================================================ ===

    public function test_it_applies_api_contact_send_throttle_middleware(): void
    {
        $middleware = Route::getRoutes()
            ->getByName('api.v1.contact')
            ->gatherMiddleware();

        $this->assertContains(
            'throttle:api-contact-send',
            $middleware,
            'POST /api/v1/contact must use the api-contact-send throttle (separate from web).',
        );
    }
}
