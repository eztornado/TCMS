<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'title' => $title,
            'excerpt' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'active',
            'price_cents' => fake()->numberBetween(500, 10000),
            'stock' => fake()->numberBetween(1, 100),
            'track_stock' => true,
            'published_at' => now(),
        ];
    }
}
