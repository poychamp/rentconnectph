<?php

namespace Tests\Feature\Admin;

use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\ListingContact;
use App\Models\Renter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminInquiryViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $response = $this->get(route('admin.inquiries.index'));

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->get(route('admin.inquiries.index'))->assertForbidden();
    }

    public function test_it_loads_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Inquiry::factory()->create();

        $response = $this->get(route('admin.inquiries.index'));

        $response->assertOk();
        $response->assertViewIs('admin.inquiries.index');

        $payload = $response->viewData('inquiries');
        $this->assertCount(1, $payload['data']);
    }

    // ---------------------------------------------------------------------
    // Sort — newest first
    // ---------------------------------------------------------------------

    public function test_it_orders_by_updated_at_desc_then_id_desc(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $base = Carbon::create(2026, 1, 1, 12, 0, 0);

        Carbon::setTestNow($base);
        $oldest = Inquiry::factory()->create();

        Carbon::setTestNow($base->copy()->addDay());
        $middle = Inquiry::factory()->create();

        Carbon::setTestNow($base->copy()->addDays(2));
        $newest = Inquiry::factory()->create();

        Carbon::setTestNow();

        $response = $this->get(route('admin.inquiries.index'));
        $response->assertOk();

        $uuids = array_map(fn ($r) => $r['uuid'], $response->viewData('inquiries')['data']);

        $this->assertSame(
            [$newest->uuid, $middle->uuid, $oldest->uuid],
            $uuids,
            'All tab must order by updated_at DESC (most-recently-touched first) — reference lookup priority',
        );
    }

    // ---------------------------------------------------------------------
    // Pagination
    // ---------------------------------------------------------------------

    public function test_it_paginates_at_10_per_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Inquiry::factory()->count(25)->create();

        $response = $this->get(route('admin.inquiries.index'));
        $response->assertOk();

        $payload = $response->viewData('inquiries');

        $this->assertSame(25, $payload['meta']['total']);
        $this->assertSame(10, $payload['meta']['per_page']);
        $this->assertCount(10, $payload['data']);
    }

    // ---------------------------------------------------------------------
    // Search — renter name substring (case-insensitive LIKE)
    // ---------------------------------------------------------------------

    public function test_it_filters_by_renter_name_substring(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $maria = Renter::factory()->create(['name' => 'Maria Cruz']);
        $pedro = Renter::factory()->create(['name' => 'Pedro Cruz']);
        $juan  = Renter::factory()->create(['name' => 'Juan Reyes']);

        Inquiry::factory()->for($maria)->create();
        Inquiry::factory()->for($pedro)->create();
        Inquiry::factory()->for($juan)->create();

        $response = $this->get(route('admin.inquiries.index', ['q' => 'cruz']));
        $response->assertOk();

        $names = array_map(fn ($r) => $r['renter']['name'], $response->viewData('inquiries')['data']);
        sort($names);

        $this->assertSame(['Maria Cruz', 'Pedro Cruz'], $names, '?q=cruz must match both Cruz renters and exclude Reyes (case-insensitive LIKE on renter.name)');
    }

    // ---------------------------------------------------------------------
    // Search — renter phone in local format (PhMobile::normalize round-trip)
    // ---------------------------------------------------------------------

    public function test_it_filters_by_renter_phone_in_local_format(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $target = Renter::factory()->create(['phone' => '+639171234567']);
        $other  = Renter::factory()->create(['phone' => '+639998887777']);

        Inquiry::factory()->for($target)->create();
        Inquiry::factory()->for($other)->create();

        // Local form 09171234567 normalizes to +639171234567 — controller's
        // PhMobile::normalize equality clause must catch the E.164-stored row.
        $response = $this->get(route('admin.inquiries.index', ['q' => '09171234567']));
        $response->assertOk();

        $rows = $response->viewData('inquiries')['data'];
        $this->assertCount(1, $rows, 'Local-format phone query must match E.164-stored renter via PhMobile::normalize round-trip');
        $this->assertSame('+639171234567', $rows[0]['renter']['phone']);
    }

    // ---------------------------------------------------------------------
    // Search — renter phone in E.164 format (raw LIKE clause)
    // ---------------------------------------------------------------------

    public function test_it_filters_by_renter_phone_in_e164_format(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $target = Renter::factory()->create(['phone' => '+639171234567']);
        $other  = Renter::factory()->create(['phone' => '+639998887777']);

        Inquiry::factory()->for($target)->create();
        Inquiry::factory()->for($other)->create();

        $response = $this->get(route('admin.inquiries.index', ['q' => '+639171234567']));
        $response->assertOk();

        $rows = $response->viewData('inquiries')['data'];
        $this->assertCount(1, $rows, 'E.164 phone query must match via raw LIKE clause');
        $this->assertSame('+639171234567', $rows[0]['renter']['phone']);
    }

    // ---------------------------------------------------------------------
    // Search — listing title substring
    // ---------------------------------------------------------------------

    public function test_it_filters_by_listing_title_substring(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $beach = Listing::factory()->verified()->create(['title' => 'Beachfront Condo']);
        $cabin = Listing::factory()->verified()->create(['title' => 'Mountain Cabin']);

        Inquiry::factory()->for($beach)->create();
        Inquiry::factory()->for($cabin)->create();

        $response = $this->get(route('admin.inquiries.index', ['q' => 'beach']));
        $response->assertOk();

        $rows = $response->viewData('inquiries')['data'];
        $this->assertCount(1, $rows, '?q=beach must match Beachfront and exclude Mountain (case-insensitive LIKE on listing.title)');
        $this->assertSame('Beachfront Condo', $rows[0]['listing']['title']);
    }

    // ---------------------------------------------------------------------
    // Search — listing contact phone via PhMobile::normalize round-trip
    // ---------------------------------------------------------------------

    public function test_it_filters_by_listing_contact_phone(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $broker = ListingContact::factory()->create(['phone' => '+639171234567']);
        $other  = ListingContact::factory()->create(['phone' => '+639998887777']);

        $brokerListing = Listing::factory()->verified()->create(['listing_contact_id' => $broker->id]);
        $otherListing  = Listing::factory()->verified()->create(['listing_contact_id' => $other->id]);

        Inquiry::factory()->for($brokerListing)->create();
        Inquiry::factory()->for($otherListing)->create();

        $response = $this->get(route('admin.inquiries.index', ['q' => '09171234567']));
        $response->assertOk();

        $rows = $response->viewData('inquiries')['data'];
        $this->assertCount(1, $rows, 'Local-format query must match listing contact via PhMobile::normalize round-trip');
        $this->assertSame('+639171234567', $rows[0]['listing']['listing_contact']['phone']);
    }

    // ---------------------------------------------------------------------
    // Search — listing contact name substring
    // ---------------------------------------------------------------------

    public function test_it_filters_by_listing_contact_name_substring(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $villanueva = ListingContact::factory()->create(['name' => 'Maria Villanueva']);
        $santos     = ListingContact::factory()->create(['name' => 'Juan Santos']);

        $villanuevaListing = Listing::factory()->verified()->create(['listing_contact_id' => $villanueva->id]);
        $santosListing     = Listing::factory()->verified()->create(['listing_contact_id' => $santos->id]);

        Inquiry::factory()->for($villanuevaListing)->create();
        Inquiry::factory()->for($santosListing)->create();

        $response = $this->get(route('admin.inquiries.index', ['q' => 'villanueva']));
        $response->assertOk();

        $rows = $response->viewData('inquiries')['data'];
        $this->assertCount(1, $rows, '?q=villanueva must match the listing contact name and exclude Santos (case-insensitive LIKE on listing_contact.name)');
        $this->assertSame('Maria Villanueva', $rows[0]['listing']['listing_contact']['name']);
    }

    // ---------------------------------------------------------------------
    // Resource shape — uuid, submitted_at, notes, renter, listing
    // ---------------------------------------------------------------------

    public function test_it_returns_resource_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $renter = Renter::factory()->create([
            'name'  => 'Maria Cruz',
            'phone' => '+639171234567',
        ]);
        $contact = ListingContact::factory()->create([
            'phone' => '+639998887777',
            'name'  => 'Maria Reyes',
            'notes' => 'Philhomes broker',
        ]);
        $listing = Listing::factory()->verified()->create([
            'title'              => 'Beachfront Condo',
            'barangay'           => 'carmen',
            'listing_contact_id' => $contact->id,
        ]);

        Inquiry::factory()
            ->for($renter)
            ->for($listing)
            ->create(['notes' => 'Owner OK with Aug 1 move-in.']);

        $response = $this->get(route('admin.inquiries.index'));
        $response->assertOk();

        $row = $response->viewData('inquiries')['data'][0];

        $this->assertEqualsCanonicalizing(
            ['uuid', 'submitted_at', 'notes', 'renter', 'listing'],
            array_keys($row),
        );

        $this->assertSame('Owner OK with Aug 1 move-in.', $row['notes']);
        $this->assertSame('Maria Cruz', $row['renter']['name']);
        $this->assertSame('+639171234567', $row['renter']['phone']);
        $this->assertSame('Beachfront Condo', $row['listing']['title']);
        $this->assertSame('+639998887777', $row['listing']['listing_contact']['phone']);
        $this->assertSame('Maria Reyes', $row['listing']['listing_contact']['name']);
    }
}
