<?php

namespace Database\Factories;

use App\Enums\DeactivationReason;
use App\Models\Listing;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class ListingLifecycleEventFactory extends Factory
{
    protected $model = ListingLifecycleEvent::class;

    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'actor_id'   => User::factory(),
            'event_type' => 'deactivated',
            'reason'     => Arr::random(DeactivationReason::toValues()),
            'notes'      => null,
            'created_at' => Carbon::now(),
        ];
    }

    public function deactivated(): self
    {
        return $this->state(fn () => [
            'event_type' => 'deactivated',
            'reason'     => Arr::random(DeactivationReason::toValues()),
        ]);
    }
}
