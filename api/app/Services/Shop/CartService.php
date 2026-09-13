<?php

namespace App\Services\Shop;

use App\Exceptions\AppException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;

/**
 * Única fuente de verdad del precio. En quantumcards había 4
 * implementaciones distintas del cálculo repartidas entre front y backend
 * (ninguna coincidía con lo cobrado por Stripe); aquí el front solo muestra
 * el quote del servidor.
 */
class CartService
{
    public function __construct(private readonly StockService $stock) {}

    /** Carrito por token (invitado) o del usuario autenticado. */
    public function resolve(?string $uuid, bool $create = false): ?Cart
    {
        if ($uuid) {
            $cart = Cart::query()->where('uuid', $uuid)->first();
            if ($cart && auth()->check() && ! $cart->user_id) {
                $cart->update(['user_id' => auth()->id()]);
            }

            return $cart;
        }

        if (! auth()->check()) {
            return $create ? Cart::create() : null;
        }

        return $create
            ? Cart::firstOrCreate(['user_id' => auth()->id()])
            : Cart::query()->where('user_id', auth()->id())->first();
    }

    public function addItem(Cart $cart, int $productId, ?int $variantId, int $quantity): CartItem
    {
        $product = Product::active()->findOrFail($productId);

        $variant = $variantId
            ? $product->variants()->whereKey($variantId)->firstOrFail()
            : $product->defaultVariant->first();

        // Producto simple sin variantes: usa la línea del propio producto.
        $variantId = $variant?->getKey();

        $existing = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->where('variant_id', $variantId)
            ->first();

        $newQuantity = ($existing?->quantity ?? 0) + max(1, $quantity);

        // En el carrito solo se valida "comprable en principio": el límite
        // real de stock lo impone el checkout (fuente única de verdad).
        if ($variant && ! $variant->inStock()) {
            throw new AppException(AppException::OUT_OF_STOCK, 'Producto agotado.', 409);
        }
        if (! $variant && $product->track_stock && $product->stock < 1) {
            throw new AppException(AppException::OUT_OF_STOCK, 'Producto agotado.', 409);
        }

        $item = $existing ?? new CartItem([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variantId,
        ]);
        $item->quantity = $newQuantity;
        $item->save();

        $this->reserve($cart);

        return $item;
    }

    public function updateItem(Cart $cart, CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            $item->delete();
        } else {
            $item->update(['quantity' => $quantity]);
        }

        $this->reserve($cart);
    }

    public function removeItem(Cart $cart, CartItem $item): void
    {
        $item->delete();
        $this->reserve($cart);
    }

    public function applyCoupon(Cart $cart, string $code): Coupon
    {
        /** @var Coupon|null $coupon */
        $coupon = Coupon::valid()->where('code', mb_strtoupper(trim($code)))->first();

        if (! $coupon) {
            throw new AppException(AppException::COUPON_INVALID, 'El cupón no es válido o ha caducado.', 422);
        }

        $quote = $this->quote($cart);
        if ($coupon->discountFor($quote['subtotal_cents']) === 0) {
            throw new AppException(AppException::COUPON_INVALID, 'El cupón no aplica a este carrito.', 422);
        }

        if ($coupon->per_user_limit && auth()->check()) {
            $used = auth()->user()->orders()->where('coupon_code', $coupon->code)->count();
            if ($used >= $coupon->per_user_limit) {
                throw new AppException(AppException::COUPON_INVALID, 'Ya has usado este cupón.', 422);
            }
        }

        $cart->update(['coupon_code' => $coupon->code]);

        return $coupon;
    }

    /**
     * Quote completo del carrito. Céntimos en todo; jamás float.
     *
     * @return array{subtotal_cents:int, discount_cents:int, tax_cents:int,
     *     shipping_cents:int, total_cents:int, currency:string, items:array}
     */
    public function quote(Cart $cart): array
    {
        $cart->loadMissing(['items.product.taxRate', 'items.variant', 'coupon', 'shippingMethod']);

        $lines = [];
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($cart->items as $item) {
            $unit = $item->unitPriceCents();
            $lineSubtotal = $unit * $item->quantity;
            $rate = $item->product->taxRatePercent();
            // Precio con impuestos incluidos (habitual en España): se desglosan.
            $lineTax = (int) round($lineSubtotal - $lineSubtotal / (1 + $rate / 100));
            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;

            $media = $item->product->cover()->first();

            $lines[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'title' => $item->product->title,
                'variant_name' => $item->variant?->name,
                'slug' => $item->product->slug,
                'sku' => $item->variant?->sku ?? $item->product->sku,
                'options' => $item->variant?->options,
                'quantity' => $item->quantity,
                'unit_price_cents' => $unit,
                'line_total_cents' => $lineSubtotal,
                'tax_rate' => $rate,
                'tax_cents' => $lineTax,
                'image_url' => $media?->thumbnail_url,
            ];
        }

        $discount = 0;
        if ($cart->coupon_code) {
            /** @var Coupon|null $coupon */
            $coupon = Coupon::valid()->where('code', $cart->coupon_code)->first();
            $discount = $coupon?->discountFor($subtotal) ?? 0;
        }

        $shipping = 0;
        if ($cart->shippingMethod) {
            $cost = $cart->shippingMethod->costFor($subtotal - $discount, $cart->country);
            if ($cost !== null) {
                $shipping = $cost;
            }
        }

        return [
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'tax_cents' => $taxTotal,
            'shipping_cents' => $shipping,
            'total_cents' => max(0, $subtotal - $discount + $shipping),
            'currency' => 'EUR',
            'items' => $lines,
            'coupon_code' => $discount > 0 ? $cart->coupon_code : null,
            'shipping_method' => $cart->shippingMethod ? [
                'id' => $cart->shippingMethod->id,
                'name' => $cart->shippingMethod->name,
            ] : null,
        ];
    }

    /** Refresca las reservas de stock del carrito con sus líneas actuales. */
    public function reserve(Cart $cart): void
    {
        $cart->loadMissing('items.variant');

        if ($cart->items->isEmpty()) {
            $this->stock->releaseCart($cart->uuid);

            return;
        }

        $lines = $cart->items
            ->filter(fn (CartItem $item) => $item->variant_id !== null)
            ->map(fn (CartItem $item) => [$item->variant, $item->quantity])
            ->all();

        if ($lines !== []) {
            $this->stock->reserve($cart->uuid, $lines);
        }
    }
}
