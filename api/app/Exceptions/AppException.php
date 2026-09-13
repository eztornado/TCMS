<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Excepción de negocio con código numérico estable (contrato con el front)
 * y su estado HTTP correcto. Inspirado en el sistema de códigos de
 * reigreengroup, corrigiendo el fallo de que todas salían con 401.
 */
class AppException extends RuntimeException
{
    public const VALIDATION = 1000;

    public const NOT_FOUND = 1001;

    public const FORBIDDEN = 1002;

    public const OUT_OF_STOCK = 1100;

    public const CART_EMPTY = 1101;

    public const COUPON_INVALID = 1102;

    public const PAYMENT_FAILED = 1103;

    public const ORDER_STATE_INVALID = 1104;

    public const EVENT_SOLD_OUT = 1200;

    public const EVENT_BOOKING_CLOSED = 1201;

    public const SCHEMA_CONFLICT = 1300;

    public function __construct(
        public readonly int $businessCode,
        string $message,
        public readonly int $status = 400,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $what = 'Recurso'): self
    {
        return new self(self::NOT_FOUND, "{$what} no encontrado.", 404);
    }

    public static function forbidden(string $message = 'No tienes permisos para esta acción.'): self
    {
        return new self(self::FORBIDDEN, $message, 403);
    }
}
