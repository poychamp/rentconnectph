<?php

namespace Database\Factories;

use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Renter;
use Illuminate\Database\Eloquent\Factories\Factory;

class InquiryFactory extends Factory
{
    protected $model = Inquiry::class;

    public function definition(): array
    {
        return [
            'renter_id'  => Renter::factory(),
            'listing_id' => Listing::factory()->verified(),
        ];
    }
}
