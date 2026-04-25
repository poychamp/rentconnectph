<?php

namespace Database\Factories;

use App\Models\ListingImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ListingImageFactory extends Factory
{
    protected $model = ListingImage::class;

    private const URLS = [
        'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&h=533&q=80',
        'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=800&h=533&q=80',
        'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=800&h=533&q=80',
        'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=800&h=533&q=80',
        'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=800&h=533&q=80',
        'https://images.unsplash.com/photo-1554995207-c18c203602cb?auto=format&fit=crop&w=800&h=533&q=80',
        'https://images.unsplash.com/photo-1568605114967-8130f3a36994?auto=format&fit=crop&w=800&h=533&q=80',
        'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=800&h=533&q=80',
    ];

    public function definition(): array
    {
        return [
            'url' => fake()->randomElement(self::URLS),
            'sort_order' => 0,
        ];
    }
}
