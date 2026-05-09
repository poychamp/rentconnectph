<?php

namespace Tests\Feature\Api\V1\Field;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingVerifiedFetchTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function makeFieldUser(): User
    {
        return User::factory()->field()->create();
    }

    private function issueFieldToken(User $user): string
    {
        return $user->createToken('field-android', ['field'])->plainTextToken;
    }

    private function actAsFieldOfficer(): User
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);
        $this->withHeaders(['Authorization' => "Bearer {$token}"]);
        return $user;
    }

    private function verifiedListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified'  => true,
            'verified_at'  => Carbon::parse('2026-05-02 09:00:00'),
            'queue_status' => QueueStatus::visited()->value,
            'assigned_to'  => $officer->id,
            'visited_at'   => Carbon::parse('2026-05-01 10:00:00'),
        ], $overrides));
    }

    // ===========================================================
    // Auth
    // ===========================================================

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $this->getJson(route('api.v1.field.listings.verified'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->getJson(route('api.v1.field.listings.verified'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_token_without_field_ability(): void
    {
        $user  = $this->makeFieldUser();
        $token = $user->createToken('hypothetical-admin-device', ['admin'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.field.listings.verified'))
            ->assertForbidden();
    }

    public function test_it_rejects_user_who_lost_field_role_after_token_issuance(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('field-android', ['field'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.field.listings.verified'))
            ->assertForbidden();
    }

    // ===========================================================
    // Slice / scope
    // ===========================================================

    public function test_it_returns_empty_data_when_officer_has_no_verified_listings(): void
    {
        $this->actAsFieldOfficer();

        $response = $this->getJson(route('api.v1.field.listings.verified'))->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_it_excludes_verified_listings_assigned_to_other_officers(): void
    {
        $marco = $this->actAsFieldOfficer();
        $other = $this->makeFieldUser();

        $mine   = $this->verifiedListingFor($marco);
        $theirs = $this->verifiedListingFor($other);

        $response = $this->getJson(route('api.v1.field.listings.verified'))->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid')->all();

        $this->assertContains($mine->uuid, $uuids);
        $this->assertNotContains($theirs->uuid, $uuids);
    }

    public function test_it_excludes_listings_not_yet_verified(): void
    {
        $marco = $this->actAsFieldOfficer();

        $verified   = $this->verifiedListingFor($marco);
        $unverified = $this->verifiedListingFor($marco, [
            'is_verified' => false,
            'verified_at' => null,
        ]);

        $response = $this->getJson(route('api.v1.field.listings.verified'))->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid')->all();

        $this->assertContains($verified->uuid, $uuids);
        $this->assertNotContains($unverified->uuid, $uuids);
    }

    public function test_it_excludes_listings_with_queue_status_assigned(): void
    {
        $marco = $this->actAsFieldOfficer();

        $verified = $this->verifiedListingFor($marco);
        $assigned = $this->verifiedListingFor($marco, [
            'queue_status' => QueueStatus::assigned()->value,
            'visited_at'   => null,
            'verified_at'  => null,
            'is_verified'  => false,
        ]);

        $response = $this->getJson(route('api.v1.field.listings.verified'))->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid')->all();

        $this->assertContains($verified->uuid, $uuids);
        $this->assertNotContains($assigned->uuid, $uuids);
    }

    public function test_it_excludes_soft_deleted_listings(): void
    {
        $marco = $this->actAsFieldOfficer();

        $alive = $this->verifiedListingFor($marco);
        $dead  = $this->verifiedListingFor($marco);
        $dead->delete();

        $response = $this->getJson(route('api.v1.field.listings.verified'))->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid')->all();

        $this->assertContains($alive->uuid, $uuids);
        $this->assertNotContains($dead->uuid, $uuids);
    }

    // ===========================================================
    // Order
    // ===========================================================

    public function test_it_orders_listings_by_verified_at_descending(): void
    {
        $marco = $this->actAsFieldOfficer();

        $oldest = $this->verifiedListingFor($marco, ['verified_at' => Carbon::parse('2026-05-01 09:00:00')]);
        $newest = $this->verifiedListingFor($marco, ['verified_at' => Carbon::parse('2026-05-03 09:00:00')]);
        $middle = $this->verifiedListingFor($marco, ['verified_at' => Carbon::parse('2026-05-02 09:00:00')]);

        $response = $this->getJson(route('api.v1.field.listings.verified'))->assertOk();
        $data = $response->json('data');

        $this->assertSame($newest->uuid, $data[0]['uuid']);
        $this->assertSame($middle->uuid, $data[1]['uuid']);
        $this->assertSame($oldest->uuid, $data[2]['uuid']);
    }

    // ===========================================================
    // Search
    // ===========================================================

    public function test_it_filters_by_search_query(): void
    {
        $marco = $this->actAsFieldOfficer();

        $safeBarangay = ['barangay' => Barangay::lapasan()->value];

        $gaisano = $this->verifiedListingFor($marco, $safeBarangay + ['title' => 'Apartment near Gaisano']);
        $carmen  = $this->verifiedListingFor($marco, $safeBarangay + ['title' => 'Studio in Carmen']);
        $this->verifiedListingFor($marco, $safeBarangay + ['title' => 'House in Kauswagan']);

        $response = $this->getJson(route('api.v1.field.listings.verified', ['q' => 'gaisano']))->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($gaisano->uuid, $data[0]['uuid']);

        $response = $this->getJson(route('api.v1.field.listings.verified', ['q' => 'carmen']))->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($carmen->uuid, $data[0]['uuid']);

        $response = $this->getJson(route('api.v1.field.listings.verified'))->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    // ===========================================================
    // Pagination
    // ===========================================================

    public function test_it_paginates_at_10_per_page_with_load_more_meta(): void
    {
        $marco = $this->actAsFieldOfficer();

        for ($i = 0; $i < 11; $i++) {
            $this->verifiedListingFor($marco, [
                'verified_at' => Carbon::parse('2026-04-20 10:00:00')->addMinutes($i),
            ]);
        }

        $page1 = $this->getJson(route('api.v1.field.listings.verified'))->assertOk();
        $this->assertCount(10, $page1->json('data'));
        $this->assertSame(1,  $page1->json('meta.current_page'));
        $this->assertSame(2,  $page1->json('meta.last_page'));
        $this->assertSame(10, $page1->json('meta.per_page'));
        $this->assertSame(11, $page1->json('meta.total'));

        $page2 = $this->getJson(route('api.v1.field.listings.verified', ['page' => 2]))->assertOk();
        $this->assertCount(1, $page2->json('data'));
        $this->assertSame(2, $page2->json('meta.current_page'));
        $this->assertSame(2, $page2->json('meta.last_page'));
    }

    // ===========================================================
    // Response shape
    // ===========================================================

    public function test_it_returns_resource_shape_with_expected_fields(): void
    {
        $marco = $this->actAsFieldOfficer();
        $listing = $this->verifiedListingFor($marco, [
            'title'         => 'Verified House',
            'type'          => ListingType::house()->value,
            'barangay'      => Barangay::carmen()->value,
            'price_monthly' => 25000,
            'directions'    => 'Near SM CDO',
            'visited_at'    => Carbon::parse('2026-05-04 10:00:00'),
            'verified_at'   => Carbon::parse('2026-05-05 14:30:00'),
            'listed_at'     => Carbon::parse('2026-05-06 09:15:00'),
        ]);

        $response = $this->getJson(route('api.v1.field.listings.verified'))->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $row = $data[0];

        $this->assertSame($listing->uuid,                            $row['uuid']);
        $this->assertSame('Verified House',                          $row['title']);
        $this->assertSame(ListingType::from($listing->type)->label,  $row['type_label']);
        $this->assertSame(Barangay::from($listing->barangay)->label, $row['barangay_label']);
        $this->assertSame(25000,                                     $row['price_monthly']);
        $this->assertSame('Near SM CDO',                             $row['directions']);
        $this->assertNull($row['display_image_url']);
        $this->assertSame('2026-05-04T10:00:00+08:00',               $row['visited_at']);
        $this->assertSame('2026-05-05T14:30:00+08:00',               $row['verified_at']);
        $this->assertSame('2026-05-06T09:15:00+08:00',               $row['listed_at']);

        $this->assertSame(
            ['uuid', 'title', 'type_label', 'barangay_label', 'price_monthly',
             'directions', 'display_image_url', 'visited_at', 'verified_at', 'listed_at'],
            array_keys($row),
        );
    }

    // ===========================================================
    // Middleware
    // ===========================================================

    public function test_it_lives_in_api_middleware_group_with_sanctum_field_abilities(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.field.listings.verified');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertContains('api', $middleware);
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('abilities:field', $middleware);
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $middleware);
        $this->assertNotContains(\App\Http\Middleware\AllowsBfcache::class, $middleware);
    }
}
