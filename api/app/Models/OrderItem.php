<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Línea de pedido con SNAPSHOT: sobrevive a borrados y cambios de precio. */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'variant_id', 'title', 'sku', 'options',
        'quantity', 'unit_price_cents', 'discount_cents', 'tax_cents', 'total_cents',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'json',
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
