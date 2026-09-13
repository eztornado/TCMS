<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'order_status_history';

    protected $fillable = ['order_id', 'from_status', 'to_status', 'user_id', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // timestamps desactivado: la fecha se fija manualmente al crear.
        static::creating(function (self $history) {
            $history->created_at ??= now();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
