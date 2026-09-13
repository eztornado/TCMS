<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'description' => $this->description,
            'status' => $this->status->value,
            'venue' => $this->venue,
            'address' => $this->address,
            'city' => $this->city,
            'capacity' => $this->capacity,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at,
            'cover' => $this->whenLoaded('cover', fn () => new MediaResource($this->cover->first())),
            'sessions' => $this->whenLoaded('sessions', fn () => $this->sessions->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'starts_at' => $s->starts_at,
                'ends_at' => $s->ends_at,
                'capacity' => $s->capacity,
                'price_cents' => $s->price_cents,
                'sale_starts_at' => $s->sale_starts_at,
                'sale_ends_at' => $s->sale_ends_at,
                'seats_left' => $s->seatsLeft(),
            ])),
            'bookings_count' => $this->whenCounted('bookings'),
            'created_at' => $this->created_at,
        ];
    }
}
