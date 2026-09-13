<?php

namespace App\Services\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\AppException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Colocación de pedidos. Todo el flujo crítico va en UNA transacción con
 * lock pesimista sobre las variantes (el fallo más grave de quantumcards
 * era un checkout sin transacción, sin stock y con un return dentro del
 * foreach: solo se cobraba el primer artículo).
 */
class CheckoutService
{
    /** Números de pedido legibles: TC-2026-000123. */
    public const PREFIX = 'TC';

    public function __construct(
        private readonly CartService $carts,
        private readonly StockService $stock,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @param array{email:string, customer_name:string,
     *     shipping_address?:array, billing_address?:array, customer_note?:string} $input
     * @param  array{success_url:string, cancel_url:string}  $urls
     * @return array{order:Order, redirect_url:?string}
     */
    public function placeOrder(Cart $cart, array $input, array $urls): array
    {
        $quote = $this->carts->quote($cart);

        if ($quote['items'] === []) {
            throw new AppException(AppException::CART_EMPTY, 'El carrito está vacío.', 422);
        }

        $result = DB::transaction(function () use ($cart, $input, $quote, $urls) {
            // Bloquea las variantes del carrito para evitar sobregiro de stock.
            $variantIds = collect($quote['items'])->pluck('variant_id')->filter()->all();
            if ($variantIds !== []) {
                $locked = collect(ProductVariant::query()
                    ->whereIn('id', $variantIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id'));

                foreach ($quote['items'] as $line) {
                    if (! $line['variant_id']) {
                        continue;
                    }
                    $variant = $locked[$line['variant_id']];
                    // Excluye las reservas del propio carrito del cómputo.
                    if ($variant->track_stock && $this->stock->available($variant, $cart->uuid) < $line['quantity']) {
                        throw new AppException(
                            AppException::OUT_OF_STOCK,
                            "Sin stock suficiente para «{$line['title']}».",
                            409,
                        );
                    }
                }
            }

            // Productos simples: mismo control con lock sobre products.
            $productIds = collect($quote['items'])->pluck('product_id')->unique()->all();
            $cart->loadMissing('items.product');
            $lockedProducts = Product::query()
                ->whereIn('id', $productIds)
                ->where('track_stock', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($cart->items as $item) {
                if ($item->variant_id !== null) {
                    continue;
                }
                $product = $lockedProducts[$item->product_id] ?? null;
                if ($product && $product->stock < $item->quantity) {
                    throw new AppException(
                        AppException::OUT_OF_STOCK,
                        "Sin stock suficiente para «{$product->title}».",
                        409,
                    );
                }
            }

            /** @var Order $order */
            $order = Order::create([
                'number' => $this->nextNumber(),
                'user_id' => $cart->user_id ?? auth()->id(),
                'email' => $input['email'],
                'customer_name' => $input['customer_name'],
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Pending,
                'subtotal_cents' => $quote['subtotal_cents'],
                'discount_cents' => $quote['discount_cents'],
                'tax_cents' => $quote['tax_cents'],
                'shipping_cents' => $quote['shipping_cents'],
                'total_cents' => $quote['total_cents'],
                'currency' => $quote['currency'],
                'coupon_code' => $quote['coupon_code'],
                'shipping_method_name' => $quote['shipping_method']['name'] ?? null,
                'shipping_address' => $input['shipping_address'] ?? null,
                'billing_address' => $input['billing_address'] ?? ($input['shipping_address'] ?? null),
                'customer_note' => $input['customer_note'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($quote['items'] as $line) {
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'],
                    'title' => $line['title'],
                    'sku' => $line['sku'],
                    'quantity' => $line['quantity'],
                    'unit_price_cents' => $line['unit_price_cents'],
                    'tax_cents' => $line['tax_cents'],
                    'total_cents' => $line['line_total_cents'],
                ]);
            }

            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => OrderStatus::Pending->value,
                'user_id' => auth()->id(),
                'note' => 'Pedido creado',
            ]);

            // Descuenta stock (variantes y simples) y libera reservas.
            $variantLines = $cart->items
                ->filter(fn ($item) => $item->variant_id !== null)
                ->map(fn ($item) => [$item->variant, $item->quantity])
                ->all();
            $productLines = $cart->items
                ->filter(fn ($item) => $item->variant_id === null)
                ->map(fn ($item) => [$item->product, $item->quantity])
                ->all();
            $this->stock->commit($cart->uuid, $variantLines, $productLines);

            if ($quote['coupon_code']) {
                Coupon::query()->where('code', $quote['coupon_code'])->increment('usage_count');
            }

            $redirect = $this->gateway->createPayment($order, $urls);

            return [$order, $redirect];
        });

        [$order, $redirect] = $result;
        $cart->items()->delete();
        $cart->update(['coupon_code' => null]);

        return ['order' => $order, 'redirect_url' => $redirect];
    }

    /**
     * Procesa un webhook de pago confirmado. Idempotente por external_id:
     * en quantumcards la confirmación era un redirect sin firma ni
     * idempotencia y se podían duplicar suscripciones recargando la URL.
     */
    public function handleWebhook(array $event, string $provider): void
    {
        $record = WebhookEvent::firstOrCreate(
            ['gateway' => $provider, 'external_id' => $event['id']],
            ['type' => $event['type'], 'payload' => $event['data']],
        );

        if ($record->processed_at) {
            return; // ya procesado
        }

        try {
            match ($event['type']) {
                'checkout.session.completed' => $this->completeCheckoutSession($event['data']['object']),
                'charge.refunded' => $this->markRefunded($event['data']['object']),
                default => null,
            };
            $record->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            $record->update(['error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function completeCheckoutSession(array $session): void
    {
        $order = Order::query()->where('number', $session['client_reference_id'] ?? $session['metadata']['order_number'] ?? null)->first();

        if (! $order) {
            return;
        }

        $paidAmount = (int) ($session['amount_total'] ?? 0);

        // El cobro debe cuadrar con el quote; si Stripe añadió algo raro, lo registramos.
        if ($paidAmount !== $order->total_cents) {
            logger()->warning('Pago con importe distinto al pedido', [
                'order' => $order->number,
                'expected' => $order->total_cents,
                'paid' => $paidAmount,
            ]);
        }

        $payment = $order->payments()->where('provider_ref', $session['id'])->first();
        $payment?->update(['status' => 'paid', 'paid_at' => now(), 'payload' => $session]);

        $order->markPaid();
    }

    protected function markRefunded(array $charge): void
    {
        $order = Order::query()
            ->whereHas('payments', fn ($q) => $q->where('provider_ref', $charge['payment_intent'] ?? null))
            ->first();

        if (! $order) {
            return;
        }

        $order->forceFill(['payment_status' => PaymentStatus::Refunded])->save();

        if ($order->status !== OrderStatus::Refunded) {
            $order->transitionTo(OrderStatus::Refunded, null, 'Reembolso confirmado por la pasarela');
        }

        $order->payments()->where('provider_ref', $charge['payment_intent'] ?? null)
            ->update(['status' => 'refunded', 'refunded_at' => now()]);
    }

    /** Marca un pago como fallido (p. ej. al caducar la sesión de checkout). */
    public function failPayment(Payment $payment, string $reason = ''): void
    {
        $payment->update(['status' => 'failed', 'payload' => ['reason' => $reason]]);
    }

    protected function nextNumber(): string
    {
        $year = now()->format('Y');
        $sequence = DB::table('orders')->whereYear('created_at', $year)->count() + 1;

        // Reintenta si hay colisión concurrente (índice unique de respaldo).
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $number = sprintf('%s-%s-%06d', self::PREFIX, $year, $sequence + $attempt);
            if (! Order::query()->where('number', $number)->exists()) {
                return $number;
            }
        }

        return self::PREFIX.'-'.$year.'-'.Str::upper(Str::random(6));
    }
}
