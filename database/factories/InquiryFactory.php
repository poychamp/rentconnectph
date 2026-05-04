<?php

namespace Database\Factories;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Renter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class InquiryFactory extends Factory
{
    protected $model = Inquiry::class;

    public function definition(): array
    {
        return [
            'renter_id'  => Renter::factory(),
            'listing_id' => Listing::factory()->verified(),
            'status'     => InquiryStatus::new()->value,
        ];
    }

    public function rejected(?User $actor = null): static
    {
        return $this->state(fn () => [
            'status'      => InquiryStatus::rejected()->value,
            'rejected_at' => Carbon::now(),
            'rejected_by' => $actor?->id ?? User::factory()->superAdmin(),
        ]);
    }

    public function handedOff(?User $actor = null): static
    {
        return $this->state(fn () => [
            'status'        => InquiryStatus::handedOff()->value,
            'handed_off_at' => Carbon::now(),
            'handed_off_by' => $actor?->id ?? User::factory()->superAdmin(),
        ]);
    }
}
