<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class ShippingMethod extends BaseModel
{
    protected $fillable = [
        'name', 'description', 'type', 'cost_cents', 'free_over_cents',
        'min_subtotal_cents', 'max_subtotal_cents', 'country', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'cost_cents' => 'integer',
            'free_over_cents' => 'integer',
            'min_subtotal_cents' => 'integer',
            'max_subtotal_cents' => 'integer',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort');
    }

    /** Coste aplicable a un subtotal (en céntimos). Null si no es aplicable. */
    public function costFor(int $subtotalCents, ?string $country = null): ?int
    {
        if ($this->country && $country && $this->country !== $country) {
            return null;
        }
        if ($this->min_subtotal_cents && $subtotalCents < $this->min_subtotal_cents) {
            return null;
        }
        if ($this->max_subtotal_cents && $subtotalCents > $this->max_subtotal_cents) {
            return null;
        }
        if ($this->free_over_cents && $subtotalCents >= $this->free_over_cents) {
            return 0;
        }

        return $this->cost_cents;
    }
}
