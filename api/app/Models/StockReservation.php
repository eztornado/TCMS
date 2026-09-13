<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reserva temporal de stock desde que el carrito se crea hasta que el pago
 * confirma (o la reserva caduca). Evita la race condition estructural de
 * quantumcards, donde el stock nunca se comprobaba ni decrementaba.
 */
class StockReservation extends Model
{
    protected $fillable = ['variant_id', 'quantity', 'cart_uuid', 'order_id', 'expires_at'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'expires_at' => 'datetime'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeForCart(Builder $query, string $cartUuid): Builder
    {
        return $query->where('cart_uuid', $cartUuid);
    }
}
