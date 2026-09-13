<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Services\Shop\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Carrito server-side identificado por uuid (invitados) o usuario. El front
 * solo guarda el uuid y muestra el quote devuelto; el cálculo del precio
 * vive aquí y en ningún otro sitio.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->carts->resolve($request->input('cart_uuid'), create: true);

        return response()->json($this->payload($cart));
    }

    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cart_uuid' => ['nullable', 'uuid'],
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['integer', 'min:1', 'max:99'],
        ]);

        $cart = $this->carts->resolve($data['cart_uuid'] ?? null, create: true);
        $this->carts->addItem($cart, $data['product_id'], $data['variant_id'] ?? null, $data['quantity'] ?? 1);

        return response()->json($this->payload($cart->refresh()));
    }

    public function updateItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $data = $request->validate([
            'cart_uuid' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $cart = $this->carts->resolve($data['cart_uuid']);
        abort_unless($cart && $cartItem->cart_id === $cart->id, 404);

        $this->carts->updateItem($cart, $cartItem, $data['quantity']);

        return response()->json($this->payload($cart->refresh()));
    }

    public function removeItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $cart = $this->carts->resolve($request->input('cart_uuid'));
        abort_unless($cart && $cartItem->cart_id === $cart->id, 404);

        $this->carts->removeItem($cart, $cartItem);

        return response()->json($this->payload($cart->refresh()));
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cart_uuid' => ['required', 'uuid'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $cart = $this->carts->resolve($data['cart_uuid']);
        abort_unless($cart, 404);

        $this->carts->applyCoupon($cart, $data['code']);

        return response()->json($this->payload($cart->refresh()));
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->carts->resolve($request->input('cart_uuid'));
        abort_unless($cart, 404);

        $cart->update(['coupon_code' => null]);

        return response()->json($this->payload($cart->refresh()));
    }

    public function setShipping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cart_uuid' => ['required', 'uuid'],
            'shipping_method_id' => ['required', 'integer'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $cart = $this->carts->resolve($data['cart_uuid']);
        abort_unless($cart, 404);

        $cart->update([
            'shipping_method_id' => $data['shipping_method_id'],
            'country' => $data['country'] ?? $cart->country,
        ]);

        return response()->json($this->payload($cart->refresh()));
    }

    protected function payload($cart): array
    {
        $quote = $this->carts->quote($cart);

        return [
            'data' => $quote,
            'quote' => $quote,
            'cart_uuid' => $cart->uuid,
            'cart' => ['uuid' => $cart->uuid],
        ];
    }
}
