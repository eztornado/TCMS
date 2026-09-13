<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Cupón validado SIEMPRE en servidor (en quantumcards la tabla existía y
 * nadie la leía: el descuento estaba comentado en el checkout).
 */
class Coupon extends BaseModel
{
    protected $fillable = [
        'code', 'type', 'percentage', 'amount_cents', 'starts_at', 'ends_at',
        'usage_limit', 'usage_count', 'per_user_limit', 'min_subtotal_cents',
        'applies_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'float',
            'amount_cents' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'per_user_limit' => 'integer',
            'min_subtotal_cents' => 'integer',
            'applies_to' => 'json',
            'is_active' => 'boolean',
        ];
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit'));
    }

    public function discountFor(int $subtotalCents): int
    {
        if ($this->min_subtotal_cents && $subtotalCents < $this->min_subtotal_cents) {
            return 0;
        }

        return $this->type === 'percentage'
            ? (int) round($subtotalCents * $this->percentage / 100)
            : min($this->amount_cents, $subtotalCents);
    }
}
