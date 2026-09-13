<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Shop\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->when($request->q, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->payment_status, fn ($q, $ps) => $q->where('payment_status', $ps))
            ->sortableBy(['id', 'number', 'total_cents', 'placed_at', 'created_at'], $request)
            ->paginate($request->integer('per_page', 25));

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['items', 'statusHistory.user', 'payments']));
    }

    /** Transición de estado con validación de máquina de estados. */
    public function changeStatus(Request $request, Order $order): OrderResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $order->transitionTo(OrderStatus::from($data['status']), $request->user()->id, $data['note'] ?? null);

        return new OrderResource($order->load(['items', 'statusHistory.user', 'payments']));
    }

    /**
     * Reembolso (total o parcial) vía la pasarela. En quantumcards la tabla
     * returns existía sin controlador y no había refunds de Stripe.
     */
    public function refund(Request $request, Order $order, StripeGateway $stripe): OrderResource
    {
        $data = $request->validate([
            'amount_cents' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = $order->payments()->where('status', 'paid')->latest()->first();

        if (! $payment) {
            abort(422, 'El pedido no tiene ningún pago cobrado.');
        }

        $amount = $data['amount_cents'] ?? $payment->amount_cents - $payment->refund_amount_cents;

        if ($amount <= 0) {
            abort(422, 'No queda importe pendiente de reembolso.');
        }

        if ($stripe->configured()) {
            $stripe->refund($payment->provider_ref, $amount, [
                'order_number' => $order->number,
                'reason' => $data['reason'] ?? '',
            ]);
        }

        $payment->increment('refund_amount_cents', $amount);
        $fullyRefunded = $payment->refund_amount_cents >= $payment->amount_cents;

        $payment->update([
            'status' => $fullyRefunded ? 'refunded' : 'pending',
            'refunded_at' => $fullyRefunded ? now() : null,
        ]);

        $order->transitionTo(OrderStatus::Refunded, $request->user()->id, $data['reason'] ?? 'Reembolso');

        return new OrderResource($order->load(['items', 'statusHistory.user', 'payments']));
    }
}
