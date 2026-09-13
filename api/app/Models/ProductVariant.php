<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'name', 'options', 'price_cents', 'compare_at_cents',
        'cost_cents', 'stock', 'track_stock', 'weight_grams', 'is_default', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'json',
            'price_cents' => 'integer',
            'compare_at_cents' => 'integer',
            'cost_cents' => 'integer',
            'stock' => 'integer',
            'track_stock' => 'boolean',
            'weight_grams' => 'integer',
            'is_default' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxRatePercent(): float
    {
        return $this->product->taxRatePercent();
    }

    public function inStock(): bool
    {
        return ! $this->track_stock || $this->stock > 0;
    }
}
