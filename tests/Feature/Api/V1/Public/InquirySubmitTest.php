<?php

namespace Tests\Feature\Api\V1\Public;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Renter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class InquirySubmitTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('api-inquiry-submit:127.0.0.1');
        RateLimiter::clear('inquiry-submit:127.0.0.1');
    }

    private function url(Listing $listing): string
    {
        return route('api.v1.listings.inquiries.store', ['listing' => $listing->uuid]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'  => 'Maria Cruz',
            'phone' => '09171234567',
        ], $overrides);
    }

    // === ============================================================ ===
    //                              Auth
    // === ============================================================ ===

    public function test_it_allows_guest_with_no_token(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->postJson($this->url($listing), $this->validPayload());

        $response->assertOk();
    }

    // === ============================================================ ===
    //                            Validation
    // === ============================================================ ===

    public function test_it_validates_required_name(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->postJson($this->url($listing), $this->validPayload([
            'name' => '',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'name' => 'Name is required.',
        ]);
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_validates_name_max_length(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->postJson($this->url($listing), $this->validPayload([
            'name' => str_repeat('a', 121),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'name' => 'Name must be 120 characters or fewer.',
        ]);
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_validates_required_phone(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->postJson($this->url($listing), $this->validPayload([
            'phone' => '',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'phone' => 'Phone is required.',
        ]);
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_validates_phone_format(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->postJson($this->url($listing), $this->validPayload([
            'phone' => '12345',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'phone' => 'Invalid PH mobile number.',
        ]);
        $this->assertSame(0, Inquiry::count());
    }

    // === ============================================================ ===
    //                         404 — listing scope
    // === ============================================================ ===

    public function test_it_returns_404_for_unknown_listing_uuid(): void
    {
        $unknownUuid = (string) Str::uuid();

        $response = $this->postJson(
            route('api.v1.listings.inquiries.store', ['listing' => $unknownUuid]),
            $this->validPayload(),
        );

        $response->assertNotFound();
        $this->assertSame(0, Renter::count());
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_returns_404_for_unverified_listing(): void
    {
        $listing = Listing::factory()->create(['is_verified' => false]);

        $response = $this->postJson($this->url($listing), $this->validPayload());

        $response->assertNotFound();
        $this->assertSame(0, Renter::count());
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_returns_404_for_soft_deleted_listing(): void
    {
        $listing = Listing::factory()->verified()->create();
        $listing->delete();

        $response = $this->postJson($this->url($listing), $this->validPayload());

        $response->assertNotFound();
        $this->assertSame(0, Inquiry::count());
    }

    // === ============================================================ ===
    //                  Persistence — Renter + Inquiry
    // === ============================================================ ===

    public function test_it_creates_renter_and_inquiry_for_first_time_phone(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->postJson($this->url($listing), $this->validPayload());

        $response->assertOk();

        $this->assertSame(1, Renter::count());
        $this->assertSame(1, Inquiry::count());

        $renter = Renter::first();
        $this->assertSame('+639171234567', $renter->phone);
        $this->assertSame('Maria Cruz', $renter->name);
        $this->assertNull($renter->is_qualified);

        $inquiry = Inquiry::first();
        $this->assertSame($renter->id, $inquiry->renter_id);
        $this->assertSame($listing->id, $inquiry->listing_id);
        $this->assertSame(InquiryStatus::new()->value, $inquiry->status);
    }

    public function test_it_normalizes_local_format_to_e164(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->postJson($this->url($listing), $this->validPayload(['phone' => '09171234567']));

        $this->assertSame('+639171234567', Renter::sole()->phone);
    }

    public function test_it_normalizes_plus_63_format_to_e164(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->postJson($this->url($listing), $this->validPayload(['phone' => '+639171234567']));

        $this->assertSame('+639171234567', Renter::sole()->phone);
    }

    public function test_it_normalizes_bare_9_format_to_e164(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->postJson($this->url($listing), $this->validPayload(['phone' => '9171234567']));

        $this->assertSame('+639171234567', Renter::sole()->phone);
    }

    public function test_it_reuses_existing_renter_for_repeat_phone(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->postJson($this->url($listing), $this->validPayload([
            'name'  => 'First Submission',
            'phone' => '09171234567',
        ]));

        $this->postJson($this->url($listing), $this->validPayload([
            'name'  => 'Different Name',
            'phone' => '+639171234567',
        ]));

        $this->assertSame(1, Renter::count(), 'Same normalized phone should reuse the Renter row');
        $this->assertSame(2, Inquiry::count(), 'Each submission writes its own Inquiry row');
    }

    public function test_it_does_not_overwrite_renter_name_on_repeat_inquiry(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->postJson($this->url($listing), $this->validPayload([
            'name'  => 'Original Name',
            'phone' => '09171234567',
        ]));

        $this->postJson($this->url($listing), $this->validPayload([
            'name'  => 'Different Name',
            'phone' => '09171234567',
        ]));

        $this->assertSame('Original Name', Renter::sole()->name);
    }

    public function test_it_preserves_existing_renter_notes_on_repeat_inquiry(): void
    {
        $listing = Listing::factory()->verified()->create();

        $existingRenter = Renter::factory()->create([
            'phone' => '+639175551234',
            'notes' => 'Graveyard shift; late check-in OK with prior owner.',
        ]);

        $this->postJson($this->url($listing), $this->validPayload([
            'name'  => 'Different Name',
            'phone' => '09175551234',
        ]));

        $existingRenter->refresh();
        $this->assertSame(
            'Graveyard shift; late check-in OK with prior owner.',
            $existingRenter->notes,
            'Existing renter notes from prior handoff must not be touched by a fresh public inquiry.',
        );
    }

    // === ============================================================ ===
    //                      Security — payload boundaries
    // === ============================================================ ===

    public function test_it_silently_ignores_status_in_request_payload(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->postJson($this->url($listing), $this->validPayload([
            'status' => InquiryStatus::rejected()->value,
        ]));

        $this->assertSame(InquiryStatus::new()->value, Inquiry::sole()->status);
    }

    public function test_it_silently_ignores_is_qualified_in_request_payload(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->postJson($this->url($listing), $this->validPayload([
            'is_qualified' => true,
        ]));

        $this->assertNull(Renter::sole()->is_qualified);
    }

    public function test_it_silently_ignores_notes_in_request_payload(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->postJson($this->url($listing), $this->validPayload([
            'notes'        => 'attacker inquiry notes payload',
            'renter_notes' => 'attacker renter notes payload',
        ]));

        $this->assertNull(
            Inquiry::sole()->notes,
            'Public API inquiry submission must NOT write inquiry.notes — that column is admin-only (set during handoff).',
        );
        $this->assertNull(
            Renter::sole()->notes,
            'Public API inquiry submission must NOT write renter.notes — that column is admin-only (set during handoff).',
        );
    }

    // === ============================================================ ===
    //                          Response shape
    // === ============================================================ ===

    public function test_it_returns_exact_success_envelope(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->postJson($this->url($listing), $this->validPayload());

        $response->assertOk();
        $response->assertExactJson(['success' => true]);
    }

    // === ============================================================ ===
    //                  Throttle — separate from web limiter
    // === ============================================================ ===

    public function test_it_throttles_after_five_submissions_in_an_hour(): void
    {
        $listing = Listing::factory()->verified()->create();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson($this->url($listing), $this->validPayload());
            $response->assertOk();
        }

        $sixth = $this->postJson($this->url($listing), $this->validPayload());
        $sixth->assertStatus(429);
    }

    public function test_its_throttle_is_independent_from_web_inquiry_submit_limiter(): void
    {
        // Web's `inquiry-submit` limiter is shared across web POSTs to /inquiries.
        // The API uses `api-inquiry-submit` — exhausting the web bucket must NOT
        // exhaust the API bucket. Pre-burn the web bucket, then prove API still works.
        $listing = Listing::factory()->verified()->create();

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('inquiry-submit:127.0.0.1');
        }

        $this->assertTrue(
            RateLimiter::tooManyAttempts('inquiry-submit:127.0.0.1', 5),
            'Sanity check: web limiter should be exhausted after 5 hits.',
        );

        $response = $this->postJson($this->url($listing), $this->validPayload());

        $response->assertOk();
    }

    // === ============================================================ ===
    //                            Middleware
    // === ============================================================ ===

    public function test_it_applies_api_inquiry_submit_throttle_middleware(): void
    {
        $middleware = Route::getRoutes()
            ->getByName('api.v1.listings.inquiries.store')
            ->gatherMiddleware();

        $this->assertContains(
            'throttle:api-inquiry-submit',
            $middleware,
            'POST /api/v1/listings/{uuid}/inquiries must use the api-inquiry-submit throttle (separate from web).',
        );
    }
}
