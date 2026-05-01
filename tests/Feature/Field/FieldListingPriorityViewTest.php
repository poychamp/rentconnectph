<?php

namespace Tests\Feature\Field;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class FieldListingPriorityViewTest extends TestCase
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

    public function test_it_redirects_guest_to_login(): void
    {
        $this->get(route('field.priority.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_user_lacking_listings_field_work_permission(): void
    {
        $orphan = User::factory()->create();
        $this->actingAs($orphan, 'admin');

        $this->get(route('field.priority.index'))
            ->assertForbidden();
    }

    public function test_it_returns_only_priority_listings_assigned_to_the_current_officer(): void
    {
        $marco = $this->asMarco();
        $listingMarcoA = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $listingMarcoB = $this->priorityListingFor($marco, ['field_priority_order' => 2]);
        $listingMarcoNonPriority = $this->priorityListingFor($marco, ['is_field_priority' => false]);

        $carlo = User::factory()->field()->create();
        $listingCarlo = $this->priorityListingFor($carlo, ['field_priority_order' => 1]);

        $response = $this->get(route('field.priority.index'));

        $response->assertOk();
        $payload = $response->viewData('priority')['data'];

        $uuids = collect($payload)->pluck('uuid')->all();
        $this->assertContains($listingMarcoA->uuid, $uuids);
        $this->assertContains($listingMarcoB->uuid, $uuids);
        $this->assertNotContains($listingMarcoNonPriority->uuid, $uuids);
        $this->assertNotContains($listingCarlo->uuid, $uuids);
    }

    public function test_it_excludes_listings_with_queue_status_other_than_assigned(): void
    {
        $marco = $this->asMarco();
        $assigned = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $unassigned = $this->priorityListingFor($marco, [
            'queue_status'         => QueueStatus::unassigned()->value,
            'field_priority_order' => 2,
        ]);

        $response = $this->get(route('field.priority.index'));
        $payload = $response->viewData('priority')['data'];

        $uuids = collect($payload)->pluck('uuid')->all();
        $this->assertContains($assigned->uuid, $uuids);
        $this->assertNotContains($unassigned->uuid, $uuids);
    }

    public function test_it_returns_resource_payload_with_expected_shape(): void
    {
        $marco = $this->asMarco();
        $listing = $this->priorityListingFor($marco, [
            'title'                => 'Apartment near Capitol',
            'type'                 => ListingType::apartment()->value,
            'barangay'             => Barangay::lapasan()->value,
            'price_monthly'        => 12500,
            'directions'           => 'Past the green gate.',
            'field_priority_order' => 1,
        ]);

        $response = $this->get(route('field.priority.index'));
        $row = collect($response->viewData('priority')['data'])->firstWhere('uuid', $listing->uuid);

        $this->assertNotNull($row);
        $this->assertSame($listing->uuid, $row['uuid']);
        $this->assertSame('Apartment near Capitol', $row['title']);
        $this->assertSame(ListingType::apartment()->label, $row['type_label']);
        $this->assertSame(Barangay::lapasan()->label, $row['barangay_label']);
        $this->assertSame(12500, $row['price_monthly']);
        $this->assertSame('Past the green gate.', $row['directions']);
        $this->assertArrayHasKey('display_image_url', $row);
        $this->assertSame(1, $row['field_priority_order']);
        $this->assertArrayHasKey('assigned_at', $row);

        $this->assertArrayNotHasKey('contact_phone', $row);
        $this->assertArrayNotHasKey('contact_type_label', $row);
        $this->assertArrayNotHasKey('source_site_label', $row);
        $this->assertArrayNotHasKey('source_url', $row);
        $this->assertArrayNotHasKey('is_field_priority', $row);
    }

    public function test_it_self_heals_irregular_field_priority_order_on_read(): void
    {
        $marco = $this->asMarco();
        $regular        = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $nullOrder      = $this->priorityListingFor($marco, ['field_priority_order' => null]);
        $duplicateOrder = $this->priorityListingFor($marco, ['field_priority_order' => 1]);

        $this->get(route('field.priority.index'))->assertOk();

        $orders = collect([$regular, $nullOrder, $duplicateOrder])
            ->map(fn ($l) => $l->fresh()->field_priority_order)
            ->all();

        $this->assertCount(3, array_unique($orders));
        foreach ($orders as $o) {
            $this->assertNotNull($o);
        }
    }

    public function test_it_does_not_paginate_results(): void
    {
        $marco = $this->asMarco();
        for ($i = 1; $i <= 15; $i++) {
            $this->priorityListingFor($marco, ['field_priority_order' => $i]);
        }

        $response = $this->get(route('field.priority.index'));
        $payload = $response->viewData('priority');

        $this->assertCount(15, $payload['data']);
        $this->assertArrayNotHasKey('links', $payload);
        $this->assertArrayNotHasKey('meta', $payload);
    }
}
