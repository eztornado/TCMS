<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'provider_ref', 'amount_cents', 'currency',
        'status', 'payload', 'paid_at', 'refund_amount_cents', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'refund_amount_cents' => 'integer',
            'payload' => 'json',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
