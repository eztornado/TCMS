<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('??????'),
            'type' => 'percentage',
            'percentage' => 10,
            'is_active' => true,
        ];
    }

    public function fixed(int $cents): static
    {
        return $this->set('type', 'fixed')->set('percentage', null)->set('amount_cents', $cents);
    }

    public function expired(): static
    {
        return $this->set('ends_at', now()->subDay());
    }
}
