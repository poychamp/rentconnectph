<?php

namespace Tests\Feature\Field;

use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class FieldListingPreviewViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function asMarco(): User
    {
        $marco = User::factory()->field()->create();
        $this->actingAs($marco, 'admin');
        return $marco;
    }

    private function submittedListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified'  => false,
            'verified_at'  => null,
            'queue_status' => QueueStatus::visited()->value,
            'assigned_to'  => $officer->id,
            'assigned_at'  => Carbon::parse('2026-04-29 10:00:00'),
            'visited_at'   => Carbon::parse('2026-04-30 14:00:00'),
        ], $overrides));
    }

    private function verifiedListingFor(User $officer, array $overrides = []): Listing
    {
        return $this->submittedListingFor($officer, array_merge([
            'is_verified' => true,
            'verified_at' => Carbon::parse('2026-04-30 16:00:00'),
        ], $overrides));
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $marco = User::factory()->field()->create();
        $listing = $this->submittedListingFor($marco);

        $this->get(route('field.listings.preview', $listing->uuid))
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_listings_field_work_permission(): void
    {
        $orphan = User::factory()->create();
        $this->actingAs($orphan, 'admin');

        $marco = User::factory()->field()->create();
        $listing = $this->submittedListingFor($marco);

        $this->get(route('field.listings.preview', $listing->uuid))
            ->assertForbidden();
    }

    public function test_it_returns_404_when_listing_is_not_assigned_to_the_current_officer(): void
    {
        $this->asMarco();
        $carlo = User::factory()->field()->create();
        $listing = $this->submittedListingFor($carlo);

        $this->get(route('field.listings.preview', $listing->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_404_when_listing_queue_status_is_not_visited(): void
    {
        $marco = $this->asMarco();
        $assigned   = $this->submittedListingFor($marco, ['queue_status' => QueueStatus::assigned()->value]);
        $unassigned = $this->submittedListingFor($marco, ['queue_status' => QueueStatus::unassigned()->value]);
        $dead       = $this->submittedListingFor($marco, ['queue_status' => QueueStatus::dead()->value]);

        $this->get(route('field.listings.preview', $assigned->uuid))->assertNotFound();
        $this->get(route('field.listings.preview', $unassigned->uuid))->assertNotFound();
        $this->get(route('field.listings.preview', $dead->uuid))->assertNotFound();
    }

    public function test_it_returns_review_page_with_eager_loaded_relations(): void
    {
        $marco = $this->asMarco();
        $listing = $this->submittedListingFor($marco);

        $response = $this->get(route('field.listings.preview', $listing->uuid));
        $response->assertOk();

        $viewListing = $response->viewData('listing');
        $this->assertNotNull($viewListing);
        $this->assertSame($listing->id,   $viewListing->id);
        $this->assertSame($listing->uuid, $viewListing->uuid);

        $this->assertTrue(
            $viewListing->relationLoaded('images'),
            'images relation must be eager-loaded for the photo gallery card',
        );
        $this->assertTrue(
            $viewListing->relationLoaded('amenities'),
            'amenities relation must be eager-loaded for the amenities card',
        );
    }

    public function test_it_loads_preview_for_verified_listings_assigned_to_the_current_officer(): void
    {
        $marco = $this->asMarco();
        $listing = $this->verifiedListingFor($marco);

        $response = $this->get(route('field.listings.preview', $listing->uuid));

        $response->assertOk();
        $viewListing = $response->viewData('listing');
        $this->assertSame($listing->uuid, $viewListing->uuid);
        $this->assertTrue((bool) $viewListing->is_verified);
    }

    public function test_it_passes_default_from_origin_as_submitted_when_query_param_missing(): void
    {
        $marco = $this->asMarco();
        $listing = $this->submittedListingFor($marco);

        $response = $this->get(route('field.listings.preview', $listing->uuid));

        $response->assertOk();
        $this->assertSame('submitted', $response->viewData('from'));
    }

    public function test_it_passes_from_origin_as_verified_when_query_param_is_verified(): void
    {
        $marco = $this->asMarco();
        $listing = $this->verifiedListingFor($marco);

        $response = $this->get(route('field.listings.preview', $listing->uuid) . '?from=verified');

        $response->assertOk();
        $this->assertSame('verified', $response->viewData('from'));
    }

    public function test_it_falls_back_to_submitted_when_from_query_param_is_invalid(): void
    {
        $marco = $this->asMarco();
        $listing = $this->submittedListingFor($marco);

        $response = $this->get(route('field.listings.preview', $listing->uuid) . '?from=garbage');

        $response->assertOk();
        $this->assertSame('submitted', $response->viewData('from'));
    }
}
