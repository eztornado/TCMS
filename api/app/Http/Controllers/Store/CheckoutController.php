<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\Shop\CartService;
use App\Services\Shop\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly CartService $carts,
    ) {}

    /**
     * Guest checkout permitido: crea el pedido, confirma stock y devuelve la
     * URL de pago. El total lo decide el servidor (quote), nunca el front.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cart_uuid' => ['required', 'uuid'],
            'email' => ['required', 'email'],
            'customer_name' => ['required', 'string', 'max:255'],
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.name' => ['required_with:shipping_address', 'string', 'max:255'],
            'shipping_address.line1' => ['required_with:shipping_address', 'string', 'max:255'],
            'shipping_address.city' => ['required_with:shipping_address', 'string', 'max:255'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:16'],
            'shipping_address.country' => ['nullable', 'string', 'size:2'],
            'billing_address' => ['nullable', 'array'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'success_url' => ['required', 'url'],
            'cancel_url' => ['required', 'url'],
        ]);

        $cart = $this->carts->resolve($data['cart_uuid']);

        if (! $cart) {
            return response()->json(['message' => 'Carrito no encontrado.'], 404);
        }

        $result = $this->checkout->placeOrder($cart, $data, [
            'success_url' => $data['success_url'].(str_contains($data['success_url'], '?') ? '&' : '?').'order={ORDER}',
            'cancel_url' => $data['cancel_url'],
        ]);

        return response()->json([
            'data' => new OrderResource($result['order']->load('items')),
            'redirect_url' => $result['redirect_url'],
            'message' => 'Pedido creado.',
        ], 201);
    }
}
