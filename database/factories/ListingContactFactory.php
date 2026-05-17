<?php

namespace Database\Factories;

use App\Models\ListingContact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ListingContactFactory extends Factory
{
    protected $model = ListingContact::class;

    public function definition(): array
    {
        return [
            'name'  => fake()->name(),
            'phone' => fake()->numerify('+639#########'),
            'notes' => null,
        ];
    }
}
