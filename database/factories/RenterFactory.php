<?php

namespace Database\Factories;

use App\Models\Renter;
use Illuminate\Database\Eloquent\Factories\Factory;

class RenterFactory extends Factory
{
    protected $model = Renter::class;

    public function definition(): array
    {
        return [
            'name'         => fake()->name(),
            'phone'        => '+639' . fake()->numerify('#########'),
            'is_qualified' => false,
        ];
    }

    public function qualified(): static
    {
        return $this->state(['is_qualified' => true]);
    }
}
