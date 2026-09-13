<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Consulta pública de un pedido por número + email (guest tracking sin
 * autenticación). El front de quantumcards inventaba el número con
 * Math.random(); aquí el número es el identificador legible.
 */
class OrderController extends Controller
{
    public function show(Request $request, string $number): OrderResource
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $order = Order::query()
            ->where('number', $number)
            ->where('email', $data['email'])
            ->firstOrFail();

        return new OrderResource($order->load(['items', 'payments']));
    }
}
