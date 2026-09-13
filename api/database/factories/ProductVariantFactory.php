<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => Str::upper(Str::random(8)),
            'name' => fake()->randomElement(['Talla S', 'Talla M', 'Talla L']),
            'price_cents' => fake()->numberBetween(500, 10000),
            'stock' => fake()->numberBetween(1, 50),
            'track_stock' => true,
            'is_default' => false,
        ];
    }
}
