<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Event extends BaseModel
{
    protected $fillable = [
        'slug', 'title', 'excerpt', 'description', 'status', 'venue', 'address',
        'city', 'capacity', 'price_cents', 'currency', 'is_featured',
        'published_at', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'price_cents' => 'integer',
            'capacity' => 'integer',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class)->orderBy('starts_at');
    }

    public function upcomingSessions(): HasMany
    {
        return $this->sessions()->where('starts_at', '>=', now());
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function cover(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->wherePivot('collection_name', 'cover');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Published->value);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereHas('sessions', fn ($q) => $q->where('starts_at', '>=', now()));
    }

    /** Precio efectivo: la sesión más próxima con precio propio manda. */
    public function effectivePriceCents(?EventSession $session = null): ?int
    {
        return $session?->price_cents ?? $this->price_cents;
    }

    /** Plazas libres: aforo del evento (o de la sesión) menos asientos activos. */
    public function seatsLeft(?EventSession $session = null): ?int
    {
        $capacity = $session?->capacity ?? $this->capacity;
        if ($capacity === null) {
            return null; // sin límite
        }

        $taken = $this->bookings()
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->when($session, fn ($q) => $q->where('event_session_id', $session->getKey()))
            ->sum('seats');

        return max(0, $capacity - (int) $taken);
    }
}
