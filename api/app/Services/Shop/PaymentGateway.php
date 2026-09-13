<?php

namespace App\Services\Shop;

use App\Models\Order;

/**
 * Abstracción de pasarela de pago. En quantumcards Stripe estaba acoplado
 * al controlador de registro (y el webhook vacío); aquí el checkout solo
 * conoce esta interfaz: añadir Redsys/Bizum/transferencia = nueva clase.
 */
interface PaymentGateway
{
    /** Identificador usado en payments.provider y en la ruta de webhook. */
    public function name(): string;

    /**
     * Crea el pago (p. ej. una Checkout Session de Stripe) y devuelve la
     * URL a la que redirigir al cliente, o null si el pago es directo.
     *
     * @param  array{success_url:string, cancel_url:string}  $urls
     */
    public function createPayment(Order $order, array $urls): ?string;

    /** Verifica la firma de un webhook y devuelve su id externo + tipo. */
    public function verifyWebhook(string $payload, string $signatureHeader): array;
}
