<?php

namespace Tests\Feature;

use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class HomeViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_home_recently_row_returns_six_rows_sorted_by_listed_at_desc(): void
    {
        // verified_at shared across all 8 rows so it can't be the accidental
        // sort key. listed_at days are SHUFFLED so insertion order, listed_at ASC,
        // and listed_at DESC are all distinct sequences — a tied verified_at sort
        // would fall back to a DB tiebreaker (PK ASC or DESC) and produce a
        // visibly wrong order, failing the assertion below.
        $sharedVerifiedAt = Carbon::parse('2026-01-01 00:00:00');

        // listed_at days per insertion index: [3, 7, 0, 5, 1, 6, 4, 2]
        // Sort by listed_at DESC (smallest subDays first → newest first):
        //   ids[2] (0d), ids[4] (1d), ids[7] (2d), ids[0] (3d), ids[6] (4d), ids[3] (5d)
        // (then page 2: ids[5] (6d), ids[1] (7d))
        $listedDaysAgo = [3, 7, 0, 5, 1, 6, 4, 2];
        $ids = [];
        foreach ($listedDaysAgo as $i => $daysAgo) {
            $ids[$i] = Listing::factory()->withImages(1)->create([
                'is_verified' => true,
                'verified_at' => $sharedVerifiedAt,
                'listed_at'   => Carbon::now()->subDays($daysAgo),
                'type'        => 'apartment',
                'barangay'    => 'pueblo_de_oro',
            ])->id;
        }

        // Noise that must NOT appear:
        Listing::factory()->unverified()->create();
        $deactivated = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => $sharedVerifiedAt,
            'listed_at'   => Carbon::now(),
        ]);
        $deactivated->delete();

        $response = $this->get(route('home'));
        $response->assertOk();

        $payload = $response->viewData('home');
        $this->assertArrayHasKey('recently', $payload);

        // HomeResource returns 'recently' as an AnonymousResourceCollection
        // (unlike SearchResource which calls ->resolve() on its inner collection).
        // Iterate directly — JsonResource::__get forwards $card->id to the model.
        $recentlyIds = [];
        foreach ($payload['recently'] as $card) {
            $recentlyIds[] = $card->id;
        }

        // Limit(6) per HomeController::index, sorted by listed_at DESC.
        // Expected first 6 = [ids[2], ids[4], ids[7], ids[0], ids[6], ids[3]].
        $this->assertCount(6, $recentlyIds);
        $this->assertSame(
            [$ids[2], $ids[4], $ids[7], $ids[0], $ids[6], $ids[3]],
            $recentlyIds,
            'Recently row must be ordered by listed_at DESC (not insertion order, not verified_at DESC)'
        );
    }
}
