<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends BaseModel
{
    protected $fillable = [
        'reference', 'event_id', 'event_session_id', 'user_id', 'order_id',
        'customer_name', 'customer_email', 'customer_phone', 'seats', 'amount_cents',
        'currency', 'status', 'payment_status', 'notes', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'seats' => 'integer',
            'amount_cents' => 'integer',
            'meta' => 'json',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
