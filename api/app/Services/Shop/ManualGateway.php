<?php

namespace App\Services\Shop;

use App\Models\Order;

/**
 * Pasarela "manual" (transferencia/contra reembolso): el pedido queda
 * pendiente de confirmación externa. Sirve como gateway por defecto cuando
 * Stripe no está configurado y como ejemplo para añadir pasarelas propias.
 */
class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function createPayment(Order $order, array $urls): ?string
    {
        $order->payments()->create([
            'provider' => $this->name(),
            'provider_ref' => 'manual-'.uniqid(),
            'amount_cents' => $order->total_cents,
            'currency' => $order->currency,
            'status' => 'pending',
        ]);

        // Sin redirección: el pedido queda pendiente de confirmación manual.
        return null;
    }

    public function verifyWebhook(string $payload, string $signatureHeader): array
    {
        return ['id' => '', 'type' => 'manual', 'data' => []];
    }
}
