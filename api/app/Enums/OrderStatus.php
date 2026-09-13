<?php

namespace App\Enums;

/** Máquina de estados de pedido. Cada transición queda registrada en order_status_history. */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /** Transiciones válidas: pending exige pasar por processing antes de completed. */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Processing, self::Cancelled], true),
            self::Processing => in_array($target, [self::Completed, self::Cancelled, self::Refunded], true),
            self::Completed => $target === self::Refunded,
            self::Cancelled, self::Refunded => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Processing => 'En proceso',
            self::Completed => 'Completado',
            self::Cancelled => 'Cancelado',
            self::Refunded => 'Reembolsado',
        };
    }

    /** @return array<int, self> */
    public static function all(): array
    {
        return self::cases();
    }
}
