<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'title' => $title,
            'excerpt' => fake()->sentence(),
            'status' => 'published',
            'venue' => fake()->city(),
            'city' => fake()->city(),
            'capacity' => 50,
            'price_cents' => 1500,
            'currency' => 'EUR',
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->set('status', 'draft')->set('published_at', null);
    }
}
