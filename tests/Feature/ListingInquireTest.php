<?php

namespace Tests\Feature;

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

class ListingInquireTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('inquiry-submit:127.0.0.1');
    }

    private function validPayload(Listing $listing, array $overrides = []): array
    {
        return array_merge([
            'listing_uuid' => $listing->uuid,
            'name'         => 'Maria Cruz',
            'phone'        => '09171234567',
        ], $overrides);
    }

    // ---------------------------------------------------------------------
    // Renter + Inquiry creation
    // ---------------------------------------------------------------------

    public function test_it_creates_renter_and_inquiry_for_first_time_phone(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->post(route('inquiries.store'), $this->validPayload($listing));

        $response->assertRedirect(route('inquiries.success'));

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

        $this->post(route('inquiries.store'), $this->validPayload($listing, [
            'phone' => '09171234567',
        ]));

        $this->assertSame('+639171234567', Renter::sole()->phone);
    }

    public function test_it_normalizes_plus_63_format_to_e164(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->post(route('inquiries.store'), $this->validPayload($listing, [
            'phone' => '+639171234567',
        ]));

        $this->assertSame('+639171234567', Renter::sole()->phone);
    }

    public function test_it_normalizes_bare_9_format_to_e164(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->post(route('inquiries.store'), $this->validPayload($listing, [
            'phone' => '9171234567',
        ]));

        $this->assertSame('+639171234567', Renter::sole()->phone);
    }

    public function test_it_reuses_existing_renter_for_repeat_phone(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->post(route('inquiries.store'), $this->validPayload($listing, [
            'name'  => 'First Submission',
            'phone' => '09171234567',
        ]));

        // Second submission with same phone in a different format.
        $this->post(route('inquiries.store'), $this->validPayload($listing, [
            'name'  => 'Different Name',
            'phone' => '+639171234567',
        ]));

        $this->assertSame(1, Renter::count(), 'Same normalized phone should reuse the Renter row');
        $this->assertSame(2, Inquiry::count(), 'Each submission writes its own Inquiry row');
    }

    public function test_it_does_not_overwrite_renter_name_on_repeat_inquiry(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->post(route('inquiries.store'), $this->validPayload($listing, [
            'name'  => 'Original Name',
            'phone' => '09171234567',
        ]));

        $this->post(route('inquiries.store'), $this->validPayload($listing, [
            'name'  => 'Different Name',
            'phone' => '09171234567',
        ]));

        $this->assertSame('Original Name', Renter::sole()->name);
    }

    public function test_it_creates_one_renter_for_two_inquiries_on_different_listings(): void
    {
        $listingA = Listing::factory()->verified()->create();
        $listingB = Listing::factory()->verified()->create();

        $this->post(route('inquiries.store'), $this->validPayload($listingA, [
            'phone' => '09171234567',
        ]));
        $this->post(route('inquiries.store'), $this->validPayload($listingB, [
            'phone' => '09171234567',
        ]));

        $this->assertSame(1, Renter::count());
        $this->assertSame(2, Inquiry::count());

        $listingIds = Inquiry::pluck('listing_id')->sort()->values()->all();
        $this->assertSame([$listingA->id, $listingB->id], $listingIds);
    }

    public function test_it_allows_two_inquiries_from_same_renter_on_same_listing(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->post(route('inquiries.store'), $this->validPayload($listing));
        $this->post(route('inquiries.store'), $this->validPayload($listing));

        $this->assertSame(1, Renter::count());
        $this->assertSame(2, Inquiry::count());
    }

    // ---------------------------------------------------------------------
    // Listing eligibility — 404 path
    // ---------------------------------------------------------------------

    public function test_it_returns_404_for_unknown_listing_uuid(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->post(route('inquiries.store'), $this->validPayload($listing, [
            'listing_uuid' => (string) Str::uuid(),
        ]));

        $response->assertNotFound();
        $this->assertSame(0, Renter::count());
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_returns_404_for_unverified_listing(): void
    {
        $listing = Listing::factory()->create(['is_verified' => false]);

        $response = $this->post(route('inquiries.store'), $this->validPayload($listing));

        $response->assertNotFound();
        $this->assertSame(0, Renter::count());
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_returns_404_for_soft_deleted_listing(): void
    {
        $listing = Listing::factory()->verified()->create();
        $listing->delete();

        $response = $this->post(route('inquiries.store'), $this->validPayload($listing));

        $response->assertNotFound();
        $this->assertSame(0, Inquiry::count());
    }

    // ---------------------------------------------------------------------
    // Validation — 422 path
    // ---------------------------------------------------------------------

    public function test_it_validates_required_listing_uuid(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->from(route('listings.show', $listing->uuid))
            ->post(route('inquiries.store'), $this->validPayload($listing, [
                'listing_uuid' => '',
            ]));

        $response->assertRedirect(route('listings.show', $listing->uuid));
        $response->assertSessionHasErrors([
            'listing_uuid' => 'Listing reference missing. Reload the page and try again.',
        ]);
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_validates_required_name(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->from(route('listings.show', $listing->uuid))
            ->post(route('inquiries.store'), $this->validPayload($listing, [
                'name' => '',
            ]));

        $response->assertRedirect(route('listings.show', $listing->uuid));
        $response->assertSessionHasErrors([
            'name' => 'Name is required.',
        ]);
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_validates_required_phone(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->from(route('listings.show', $listing->uuid))
            ->post(route('inquiries.store'), $this->validPayload($listing, [
                'phone' => '',
            ]));

        $response->assertRedirect(route('listings.show', $listing->uuid));
        $response->assertSessionHasErrors([
            'phone' => 'Phone is required.',
        ]);
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_validates_phone_format(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->from(route('listings.show', $listing->uuid))
            ->post(route('inquiries.store'), $this->validPayload($listing, [
                'phone' => '12345',
            ]));

        $response->assertRedirect(route('listings.show', $listing->uuid));
        $response->assertSessionHasErrors([
            'phone' => 'Invalid PH mobile number.',
        ]);
        $this->assertSame(0, Inquiry::count());
    }

    public function test_it_validates_name_max_length(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->from(route('listings.show', $listing->uuid))
            ->post(route('inquiries.store'), $this->validPayload($listing, [
                'name' => str_repeat('a', 121),
            ]));

        $response->assertRedirect(route('listings.show', $listing->uuid));
        $response->assertSessionHasErrors([
            'name' => 'Name must be 120 characters or fewer.',
        ]);
        $this->assertSame(0, Inquiry::count());
    }

    // ---------------------------------------------------------------------
    // Security boundaries
    // ---------------------------------------------------------------------

    public function test_it_silently_ignores_is_qualified_in_request_payload(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->post(route('inquiries.store'), $this->validPayload($listing, [
            'is_qualified' => true,
        ]));

        $this->assertNull(Renter::sole()->is_qualified);
    }

    public function test_it_silently_ignores_notes_in_request_payload(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->post(route('inquiries.store'), $this->validPayload($listing, [
            'notes'        => 'attacker inquiry notes payload',
            'renter_notes' => 'attacker renter notes payload',
        ]));

        $this->assertNull(
            Inquiry::sole()->notes,
            'Public inquiry submission must NOT write inquiry.notes — that column is admin-only (set during handoff).',
        );
        $this->assertNull(
            Renter::sole()->notes,
            'Public inquiry submission must NOT write renter.notes — that column is admin-only (set during handoff).',
        );
    }

    public function test_it_preserves_existing_renter_notes_on_repeat_inquiry(): void
    {
        $listing = Listing::factory()->verified()->create();

        $existingRenter = Renter::factory()->create([
            'phone' => '+639175551234',
            'notes' => 'Graveyard shift; late check-in OK with prior owner.',
        ]);

        $this->post(route('inquiries.store'), $this->validPayload($listing, [
            'name'  => 'Different Name',
            'phone' => '09175551234', // same E.164 normalization → firstOrCreate hits existingRenter
        ]));

        $existingRenter->refresh();
        $this->assertSame(
            'Graveyard shift; late check-in OK with prior owner.',
            $existingRenter->notes,
            'Existing renter notes from prior handoff must not be touched by a fresh public inquiry.',
        );
    }

    // ---------------------------------------------------------------------
    // Throttling + CSRF
    // ---------------------------------------------------------------------

    public function test_it_throttles_after_five_submissions_in_an_hour(): void
    {
        $listing = Listing::factory()->verified()->create();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('inquiries.store'), $this->validPayload($listing));
            $response->assertRedirect(route('inquiries.success'));
        }

        $sixth = $this->post(route('inquiries.store'), $this->validPayload($listing));
        $sixth->assertStatus(429);
    }

    public function test_it_requires_csrf_token(): void
    {
        // Laravel's ValidateCsrfToken short-circuits in test mode via
        // $app->runningUnitTests(), so a runtime 419 can't be exercised here.
        // Verify the route is in the web middleware group instead — this
        // catches the regression we actually care about (route accidentally
        // moved out of the web group, which would drop CSRF protection).
        $middleware = Route::getRoutes()
            ->getByName('inquiries.store')
            ->gatherMiddleware();

        $this->assertContains(
            'web',
            $middleware,
            'POST /inquiries must be in the web middleware group (which applies CSRF protection).',
        );
    }

    // ---------------------------------------------------------------------
    // Redirect + flash
    // ---------------------------------------------------------------------

    public function test_it_redirects_to_inquiries_success_on_success(): void
    {
        $listing = Listing::factory()->verified()->create();

        $response = $this->post(route('inquiries.store'), $this->validPayload($listing));

        $response->assertRedirect(route('inquiries.success'));
    }

    public function test_it_does_not_flash_phone_to_session(): void
    {
        $listing = Listing::factory()->verified()->create();

        $this->post(route('inquiries.store'), $this->validPayload($listing));

        $this->assertNull(session('inquiry.phone'));
    }

    public function test_it_flashes_listing_title_to_session_on_success(): void
    {
        $listing = Listing::factory()->verified()->create(['title' => 'Beachfront Condo']);

        $this->post(route('inquiries.store'), $this->validPayload($listing));

        $this->assertSame('Beachfront Condo', session('inquiry.listing.title'));
    }

    public function test_it_flashes_listing_barangay_to_session_on_success(): void
    {
        $listing = Listing::factory()->verified()->create([
            'barangay' => 'pueblo_de_oro',
        ]);

        $this->post(route('inquiries.store'), $this->validPayload($listing));

        $this->assertSame('Pueblo de Oro', session('inquiry.listing.barangay'));
    }

    public function test_it_flashes_contact_phone_to_session_on_success(): void
    {
        $contact = ListingContact::factory()->create([
            'phone' => '+639175551234',
        ]);
        $listing = Listing::factory()->verified()->create([
            'listing_contact_id' => $contact->id,
        ]);

        $this->post(route('inquiries.store'), $this->validPayload($listing));

        $this->assertSame('+639175551234', session('inquiry.listing.listing_contact.phone'));
    }

    public function test_it_flashes_contact_name_to_session_on_success(): void
    {
        $contact = ListingContact::factory()->create([
            'name' => 'Juan Dela Cruz',
        ]);
        $listing = Listing::factory()->verified()->create([
            'listing_contact_id' => $contact->id,
        ]);

        $this->post(route('inquiries.store'), $this->validPayload($listing));

        $this->assertSame('Juan Dela Cruz', session('inquiry.listing.listing_contact.name'));
    }

    public function test_it_flashes_null_contact_name_when_listing_contact_has_no_name(): void
    {
        $contact = ListingContact::factory()->create([
            'name' => null,
        ]);
        $listing = Listing::factory()->verified()->create([
            'listing_contact_id' => $contact->id,
        ]);

        $this->post(route('inquiries.store'), $this->validPayload($listing));

        $this->assertNull(session('inquiry.listing.listing_contact.name'));
    }

    public function test_it_flashes_contact_notes_to_session_on_success(): void
    {
        $contact = ListingContact::factory()->create([
            'notes' => 'Text first before calling. Available 3-5pm.',
        ]);
        $listing = Listing::factory()->verified()->create([
            'listing_contact_id' => $contact->id,
        ]);

        $this->post(route('inquiries.store'), $this->validPayload($listing));

        $this->assertSame('Text first before calling. Available 3-5pm.', session('inquiry.listing.listing_contact.notes'));
    }

    public function test_it_flashes_null_contact_notes_when_listing_contact_has_no_notes(): void
    {
        $contact = ListingContact::factory()->create([
            'notes' => null,
        ]);
        $listing = Listing::factory()->verified()->create([
            'listing_contact_id' => $contact->id,
        ]);

        $this->post(route('inquiries.store'), $this->validPayload($listing));

        $this->assertNull(session('inquiry.listing.listing_contact.notes'));
    }

    public function test_it_flashes_contact_type_label_to_session_on_success(): void
    {
        $listing = Listing::factory()->verified()->create([
            'contact_type' => 'owner',
        ]);

        $this->post(route('inquiries.store'), $this->validPayload($listing));

        $this->assertSame('Owner', session('inquiry.listing.contact_type_label'));
    }

    public function test_it_flashes_null_contact_type_when_listing_has_no_contact_type(): void
    {
        $listing = Listing::factory()->verified()->create([
            'contact_type' => null,
        ]);

        $this->post(route('inquiries.store'), $this->validPayload($listing));

        $this->assertNull(session('inquiry.listing.contact_type_label'));
    }
}
