<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'variant_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** Línea resuelta: variante si hay, si no el producto simple. */
    public function purchasable(): Product|ProductVariant|null
    {
        return $this->variant ?? $this->product;
    }

    public function unitPriceCents(): int
    {
        return (int) ($this->variant?->price_cents ?? $this->product->effectivePriceCents());
    }
}
