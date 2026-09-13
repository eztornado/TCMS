<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Product extends BaseModel
{
    protected $fillable = [
        'slug', 'title', 'excerpt', 'description', 'status', 'sku', 'price_cents',
        'compare_at_cents', 'stock', 'track_stock', 'weight_grams', 'is_featured',
        'tax_rate_id', 'published_at', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'compare_at_cents' => 'integer',
            'stock' => 'integer',
            'track_stock' => 'boolean',
            'weight_grams' => 'integer',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort');
    }

    public function defaultVariant(): HasMany
    {
        return $this->variants()->where('is_default', true);
    }

    public function cover(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->wherePivot('collection_name', 'cover');
    }

    public function gallery(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->wherePivot('collection_name', 'gallery')
            ->orderByPivot('sort');
    }

    public function terms(): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termgable', 'termgables');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /** El precio del producto es el de su variante por defecto (o el suyo propio si es simple). */
    public function effectivePriceCents(): int
    {
        return (int) ($this->defaultVariant->first()?->price_cents ?? $this->price_cents ?? 0);
    }

    public function taxRatePercent(): float
    {
        return (float) ($this->taxRate->rate ?? (TaxRate::query()->where('is_default', true)->value('rate') ?? 0));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where(fn ($q) => $q
            ->whereNull('published_at')
            ->orWhere('published_at', '<=', now()));
    }

    public function inStock(): bool
    {
        if (! $this->track_stock) {
            return true;
        }
        if ($this->variants->isNotEmpty()) {
            return $this->variants->contains(fn (ProductVariant $v) => $v->inStock());
        }

        return $this->stock > 0;
    }
}
