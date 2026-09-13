<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSession extends BaseModel
{
    protected $fillable = [
        'event_id', 'title', 'starts_at', 'ends_at', 'capacity', 'price_cents',
        'sale_starts_at', 'sale_ends_at', 'status', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'capacity' => 'integer',
            'price_cents' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isOnSale(): bool
    {
        $now = now();

        return ($this->sale_starts_at === null || $this->sale_starts_at->lte($now))
            && ($this->sale_ends_at === null || $this->sale_ends_at->gte($now));
    }

    public function seatsLeft(): int
    {
        $capacity = $this->capacity ?? $this->event->capacity;

        if ($capacity === null) {
            return PHP_INT_MAX;
        }

        $taken = $this->event->bookings()
            ->where('event_session_id', $this->getKey())
            ->whereNotIn('status', ['cancelled'])
            ->sum('seats');

        return max(0, $capacity - (int) $taken);
    }
}
