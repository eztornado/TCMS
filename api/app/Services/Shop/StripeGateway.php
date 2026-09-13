<?php

namespace App\Services\Shop;

use App\Models\Order;
use Illuminate\Support\Facades\Config;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    public function name(): string
    {
        return 'stripe';
    }

    public function configured(): bool
    {
        return (bool) Config::get('services.stripe.secret');
    }

    private function client(): StripeClient
    {
        if (! $this->configured()) {
            throw new UnexpectedValueException('Stripe no está configurado (SERVICES_STRIPE_SECRET).');
        }

        return $this->client ??= new StripeClient(Config::get('services.stripe.secret'));
    }

    public function createPayment(Order $order, array $urls): ?string
    {
        $lineItems = $order->items->map(fn ($item) => [
            'quantity' => $item->quantity,
            'price_data' => [
                'currency' => mb_strtolower($order->currency),
                'unit_amount' => $item->unit_price_cents,
                'product_data' => [
                    'name' => $item->title,
                    'metadata' => ['sku' => $item->sku ?? '', 'order_item' => $item->id],
                ],
            ],
        ])->values()->all();

        // Los gastos de envío y el descuento de cupón viajan como líneas
        // propias para que Stripe cobre EXACTAMENTE el total del quote.
        if ($order->shipping_cents > 0) {
            $lineItems[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => mb_strtolower($order->currency),
                    'unit_amount' => $order->shipping_cents,
                    'product_data' => ['name' => 'Envío'.($order->shipping_method_name ? " — {$order->shipping_method_name}" : '')],
                ],
            ];
        }
        if ($order->discount_cents > 0) {
            $lineItems[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => mb_strtolower($order->currency),
                    'unit_amount' => -$order->discount_cents,
                    'product_data' => ['name' => 'Descuento'.($order->coupon_code ? " ({$order->coupon_code})" : '')],
                ],
            ];
        }

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $order->email,
            'client_reference_id' => $order->number,
            'metadata' => ['order_number' => $order->number],
            'payment_intent_data' => ['metadata' => ['order_number' => $order->number]],
            'line_items' => $lineItems,
            'success_url' => $urls['success_url'],
            'cancel_url' => $urls['cancel_url'],
        ]);

        $order->payments()->create([
            'provider' => $this->name(),
            'provider_ref' => $session->id,
            'amount_cents' => $order->total_cents,
            'currency' => $order->currency,
            'status' => 'pending',
        ]);

        return $session->url;
    }

    /** Reembolso total o parcial de un pago. */
    public function refund(string $paymentIntentId, int $amountCents, array $metadata = []): object
    {
        return $this->client()->refunds->create([
            'payment_intent' => $paymentIntentId,
            'amount' => $amountCents,
            'metadata' => $metadata,
        ]);
    }

    public function verifyWebhook(string $payload, string $signatureHeader): array
    {
        $secret = Config::get('services.stripe.webhook_secret');

        if (! $secret) {
            throw new UnexpectedValueException('Webhook secret de Stripe no configurado.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $secret);
        } catch (SignatureVerificationException $e) {
            throw new UnexpectedValueException('Firma de webhook inválida.', 0, $e);
        }

        return ['id' => $event->id, 'type' => $event->type, 'data' => $event->data->toArray()];
    }
}
