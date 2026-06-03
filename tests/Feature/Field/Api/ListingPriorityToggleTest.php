<?php

namespace Tests\Feature\Field\Api;

use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingPriorityToggleTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function asMarco(): User
    {
        $marco = User::factory()->field()->create();
        $this->actingAs($marco, 'admin');
        return $marco;
    }

    private function assignedListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified'       => false,
            'verified_at'       => null,
            'queue_status'      => QueueStatus::assigned()->value,
            'assigned_to'       => $officer->id,
            'assigned_at'       => Carbon::parse('2026-04-29 10:00:00'),
            'is_field_priority' => false,
        ], $overrides));
    }

    public function test_it_returns_401_for_guest(): void
    {
        $marco = User::factory()->field()->create();
        $listing = $this->assignedListingFor($marco);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertUnauthorized();
    }

    public function test_it_forbids_user_lacking_listings_field_work_permission(): void
    {
        $orphan = User::factory()->create();
        $this->actingAs($orphan, 'admin');

        $marco = User::factory()->field()->create();
        $listing = $this->assignedListingFor($marco);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertForbidden();
    }

    public function test_it_returns_422_when_listing_is_assigned_to_a_different_officer(): void
    {
        $this->asMarco();
        $carlo = User::factory()->field()->create();
        $listing = $this->assignedListingFor($carlo);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertJsonValidationErrors(['listing']);
    }

    public function test_it_returns_422_when_queue_status_is_not_assigned(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco, [
            'queue_status' => QueueStatus::unassigned()->value,
        ]);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertJsonValidationErrors(['listing']);
    }

    public function test_it_flips_is_field_priority_to_true_when_currently_false(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertOk()
            ->assertJson([
                'ok'                => true,
                'is_field_priority' => true,
                'message'           => 'Added to priority queue.',
            ]);

        $this->assertTrue((bool) $listing->fresh()->is_field_priority);
    }

    public function test_it_flips_is_field_priority_to_false_when_currently_true(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco, ['is_field_priority' => true]);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertOk()
            ->assertJson([
                'ok'                => true,
                'is_field_priority' => false,
                'message'           => 'Removed from priority queue.',
            ]);

        $this->assertFalse((bool) $listing->fresh()->is_field_priority);
    }

    public function test_it_does_not_set_field_priority_order_when_toggling_to_true(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco, [
            'is_field_priority'    => false,
            'field_priority_order' => null,
        ]);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertOk();

        $listing->refresh();
        $this->assertTrue((bool) $listing->is_field_priority);
        $this->assertNull($listing->field_priority_order);
    }

    public function test_it_clears_field_priority_order_when_toggling_to_false(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco, [
            'is_field_priority'    => true,
            'field_priority_order' => 5,
        ]);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertOk();

        $listing->refresh();
        $this->assertFalse((bool) $listing->is_field_priority);
        $this->assertNull($listing->field_priority_order);
    }

    public function test_it_does_not_change_queue_status(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertOk();

        $this->assertSame(
            QueueStatus::assigned()->value,
            $listing->fresh()->queue_status,
        );
    }

    public function test_it_does_not_record_a_lifecycle_event(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $countBefore = ListingLifecycleEvent::where('listing_id', $listing->id)->count();

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertOk();

        $countAfter = ListingLifecycleEvent::where('listing_id', $listing->id)->count();

        $this->assertSame(
            $countBefore,
            $countAfter,
            'Priority toggle is field-internal organization, not part of the listing audit trail.',
        );
    }

    public function test_consecutive_toggles_oscillate_the_persisted_state(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertOk()
            ->assertJson(['is_field_priority' => true]);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertOk()
            ->assertJson(['is_field_priority' => false]);

        $this->putJson(route('field.api.listings.priority-toggle', $listing->uuid))
            ->assertOk()
            ->assertJson(['is_field_priority' => true]);

        $this->assertTrue((bool) $listing->fresh()->is_field_priority);
    }
}
