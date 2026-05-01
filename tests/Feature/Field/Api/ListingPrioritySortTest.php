<?php

namespace Tests\Feature\Field\Api;

use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingPrioritySortTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function asMarco(): User
    {
        $marco = User::factory()->field()->create();
        $this->actingAs($marco, 'admin');
        return $marco;
    }

    private function priorityListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified'          => false,
            'verified_at'          => null,
            'queue_status'         => QueueStatus::assigned()->value,
            'assigned_to'          => $officer->id,
            'assigned_at'          => Carbon::parse('2026-04-29 10:00:00'),
            'is_field_priority'    => true,
            'field_priority_order' => null,
        ], $overrides));
    }

    public function test_it_returns_401_for_guest(): void
    {
        $this->putJson(route('field.api.listings.priority-sort'), [
            'order' => ['00000000-0000-0000-0000-000000000000'],
        ])->assertUnauthorized();
    }

    public function test_it_forbids_user_lacking_listings_field_work_permission(): void
    {
        $orphan = User::factory()->create();
        $this->actingAs($orphan, 'admin');

        $this->putJson(route('field.api.listings.priority-sort'), [
            'order' => ['00000000-0000-0000-0000-000000000000'],
        ])->assertForbidden();
    }

    public function test_it_validates_order_payload(): void
    {
        $this->asMarco();

        $this->putJson(route('field.api.listings.priority-sort'), [])
            ->assertJsonValidationErrors(['order']);

        $this->putJson(route('field.api.listings.priority-sort'), ['order' => 'not-an-array'])
            ->assertJsonValidationErrors(['order']);

        $this->putJson(route('field.api.listings.priority-sort'), ['order' => []])
            ->assertJsonValidationErrors(['order']);

        $this->putJson(route('field.api.listings.priority-sort'), ['order' => ['not-a-uuid']])
            ->assertJsonValidationErrors(['order.0']);
    }

    public function test_it_returns_422_when_one_or_more_uuids_are_not_in_the_officers_priority_pool(): void
    {
        $marco = $this->asMarco();
        $marcoListing = $this->priorityListingFor($marco, ['field_priority_order' => 1]);

        $carlo = User::factory()->field()->create();
        $carloListing = $this->priorityListingFor($carlo, ['field_priority_order' => 1]);

        $this->putJson(route('field.api.listings.priority-sort'), [
            'order' => [$marcoListing->uuid, $carloListing->uuid],
        ])->assertJsonValidationErrors(['order']);
    }

    public function test_it_persists_the_new_order(): void
    {
        $marco = $this->asMarco();
        $a = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $b = $this->priorityListingFor($marco, ['field_priority_order' => 2]);
        $c = $this->priorityListingFor($marco, ['field_priority_order' => 3]);

        $this->putJson(route('field.api.listings.priority-sort'), [
            'order' => [$c->uuid, $a->uuid, $b->uuid],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, $c->fresh()->field_priority_order);
        $this->assertSame(2, $a->fresh()->field_priority_order);
        $this->assertSame(3, $b->fresh()->field_priority_order);
    }

    public function test_it_does_not_change_queue_status(): void
    {
        $marco = $this->asMarco();
        $a = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $b = $this->priorityListingFor($marco, ['field_priority_order' => 2]);

        $this->putJson(route('field.api.listings.priority-sort'), [
            'order' => [$b->uuid, $a->uuid],
        ])->assertOk();

        $this->assertSame(QueueStatus::assigned()->value, $a->fresh()->queue_status);
        $this->assertSame(QueueStatus::assigned()->value, $b->fresh()->queue_status);
    }

    public function test_it_does_not_change_is_field_priority(): void
    {
        $marco = $this->asMarco();
        $a = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $b = $this->priorityListingFor($marco, ['field_priority_order' => 2]);

        $this->putJson(route('field.api.listings.priority-sort'), [
            'order' => [$b->uuid, $a->uuid],
        ])->assertOk();

        $this->assertTrue((bool) $a->fresh()->is_field_priority);
        $this->assertTrue((bool) $b->fresh()->is_field_priority);
    }
}
