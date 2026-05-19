<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\ListingContact;
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
        RateLimiter::clear($this->throttleKey('inquiry-submit', 'phone:+639171234567'));
        RateLimiter::clear($this->throttleKey('inquiry-submit', 'ip:127.0.0.1'));
        RateLimiter::clear($this->throttleKey('api-inquiry-submit', 'phone:+639171234567'));
        RateLimiter::clear($this->throttleKey('api-inquiry-submit', 'ip:127.0.0.1'));
    }

    // Laravel's ThrottleRequests middleware md5-hashes the cache key as
    // md5($limiterName . $limit->key) before storing. Tests pre-filling the
    // bucket must match that derivation.
    private function throttleKey(string $limiter, string $byKey): string
    {
        return md5($limiter.$byKey);
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
        $contact = ListingContact::factory()->create([
            'phone' => '+639171234567',
            'name'  => 'Juan Dela Cruz',
            'notes' => 'Text first before calling.',
        ]);
        $listing = Listing::factory()->verified()->create([
            'title'              => 'Beachfront Condo',
            'barangay'           => 'pueblo_de_oro',
            'contact_type'       => 'owner',
            'listing_contact_id' => $contact->id,
        ]);

        $response = $this->postJson($this->url($listing), $this->validPayload());

        $response->assertOk();
        $response->assertExactJson([
            'success' => true,
            'listing' => [
                'title'              => 'Beachfront Condo',
                'barangay'           => 'Pueblo de Oro',
                'contact_type_label' => 'Owner',
                'listing_contact'    => [
                    'phone' => '+639171234567',
                    'name'  => 'Juan Dela Cruz',
                    'notes' => 'Text first before calling.',
                ],
            ],
        ]);
    }

    // === ============================================================ ===
    //                  Throttle — separate from web limiter
    // === ============================================================ ===

    public function test_it_throttles_when_phone_bucket_hits_cap(): void
    {
        $listing = Listing::factory()->verified()->create();

        // Pre-fill the phone bucket to its hourly cap of 8.
        // Default validPayload phone "09171234567" normalizes to "+639171234567".
        for ($i = 0; $i < 8; $i++) {
            RateLimiter::hit($this->throttleKey('api-inquiry-submit', 'phone:+639171234567'), 3600);
        }

        $response = $this->postJson($this->url($listing), $this->validPayload());

        $response->assertStatus(429);
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_throttles_when_ip_bucket_hits_cap(): void
    {
        $listing = Listing::factory()->verified()->create();

        // Pre-fill the IP bucket to its hourly cap of 30.
        // Tests run from 127.0.0.1 by default.
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit($this->throttleKey('api-inquiry-submit', 'ip:127.0.0.1'), 3600);
        }

        $response = $this->postJson($this->url($listing), $this->validPayload());

        $response->assertStatus(429);
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_isolates_from_inquiry_submit_bucket(): void
    {
        $listing = Listing::factory()->verified()->create();

        // Burn the OTHER surface's buckets to cap. API inquiry submit must NOT
        // be affected — per the per-surface throttle-isolation convention.
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit($this->throttleKey('inquiry-submit', 'phone:+639171234567'), 3600);
            RateLimiter::hit($this->throttleKey('inquiry-submit', 'ip:127.0.0.1'), 3600);
        }

        $response = $this->postJson($this->url($listing), $this->validPayload());

        $response->assertOk();
        $this->assertSame(1, Inquiry::count());
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
