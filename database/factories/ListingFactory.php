<?php

namespace Database\Factories;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class ListingFactory extends Factory
{
    protected $model = Listing::class;

    public function definition(): array
    {
        return [
            'title' => fake()->streetName().' Suites',
            'type' => Arr::random(ListingType::toValues()),
            'price_monthly' => fake()->numberBetween(5000, 50000),
            'beds' => fake()->numberBetween(1, 4),
            'baths' => fake()->numberBetween(1, 3),
            'sqft' => fake()->numberBetween(12, 200),
            'barangay' => Arr::random(Barangay::toValues()),
            'is_verified' => true,
            'verified_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'is_featured' => false,
            'display_image_id' => null,
        ];
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    public function unverified(): static
    {
        return $this->state([
            'is_verified' => false,
            'verified_at' => null,
        ]);
    }

    public function withImages(int $count = 3): static
    {
        return $this->afterCreating(function (Listing $listing) use ($count) {
            $images = ListingImage::factory()
                ->count($count)
                ->sequence(fn ($seq) => ['sort_order' => $seq->index])
                ->create(['listing_id' => $listing->id]);

            $listing->update(['display_image_id' => $images->first()->id]);
        });
    }
}
