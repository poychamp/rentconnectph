<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class PublicInquirySuccessViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function validListingPayload(): array
    {
        return [
            'title' => 'Beachfront Condo',
            'barangay' => 'Pueblo de Oro',
            'contact_type_label' => 'Owner',
            'listing_contact' => [
                'phone' => '+639175551234',
                'name' => 'Juan Dela Cruz',
                'notes' => 'Text first before calling. Available 3-5pm.',
            ],
        ];
    }

    // === ============================== ===
    // View render + data
    // === ============================== ===

    public function test_it_uses_inquiries_success_view(): void
    {
        $response = $this->get(route('inquiries.success'));

        $response->assertOk();
        $response->assertViewIs('inquiries.success');
    }

    public function test_it_passes_listing_payload_from_session_flash_to_view(): void
    {
        $payload = $this->validListingPayload();

        $response = $this->withSession(['inquiry.listing' => $payload])
            ->get(route('inquiries.success'));

        $response->assertOk();
        $response->assertViewHas('listing', $payload);
    }

    public function test_it_passes_null_listing_when_session_flash_is_absent(): void
    {
        $response = $this->get(route('inquiries.success'));

        $response->assertOk();
        $response->assertViewHas('listing', null);
    }
}
