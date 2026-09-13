<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\AppException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'number', 'user_id', 'email', 'status', 'payment_status',
        'subtotal_cents', 'discount_cents', 'tax_cents', 'shipping_cents',
        'total_cents', 'currency', 'coupon_code', 'shipping_method_name',
        'shipping_address', 'billing_address', 'customer_note', 'metadata',
        'placed_at', 'paid_at', 'shipped_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_cents' => 'integer',
            'shipping_cents' => 'integer',
            'total_cents' => 'integer',
            'shipping_address' => 'json',
            'billing_address' => 'json',
            'metadata' => 'json',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeNumber(Builder $query, string $number): Builder
    {
        return $query->where('number', $number);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function markPaid(): void
    {
        $this->forceFill(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

        if ($this->status === OrderStatus::Pending) {
            $this->transitionTo(OrderStatus::Processing, null, 'Pago confirmado');
        }
    }

    /**
     * Transición de estado con validación de máquina de estados e historial.
     *
     * @throws AppException
     */
    public function transitionTo(OrderStatus $target, ?int $userId = null, ?string $note = null): void
    {
        $current = $this->status;

        if ($current === $target) {
            return;
        }
        if (! $current->canTransitionTo($target)) {
            throw new AppException(
                AppException::ORDER_STATE_INVALID,
                "No se puede pasar de {$current->label()} a {$target->label()}.",
                422,
            );
        }

        $this->forceFill(['status' => $target])->save();
        $this->statusHistory()->create([
            'from_status' => $current->value,
            'to_status' => $target->value,
            'user_id' => $userId,
            'note' => $note,
        ]);
    }
}
