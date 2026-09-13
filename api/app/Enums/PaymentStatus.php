<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de pago',
            self::Paid => 'Pagado',
            self::Failed => 'Fallido',
            self::Refunded => 'Reembolsado',
        };
    }
}
