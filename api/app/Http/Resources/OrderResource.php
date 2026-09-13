<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'email' => $this->email,
            'customer_name' => $this->customer_name ?? null,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'subtotal_cents' => $this->subtotal_cents,
            'discount_cents' => $this->discount_cents,
            'tax_cents' => $this->tax_cents,
            'shipping_cents' => $this->shipping_cents,
            'total_cents' => $this->total_cents,
            'currency' => $this->currency,
            'coupon_code' => $this->coupon_code,
            'shipping_method_name' => $this->shipping_method_name,
            'shipping_address' => $this->shipping_address,
            'billing_address' => $this->billing_address,
            'customer_note' => $this->customer_note,
            'placed_at' => $this->placed_at,
            'paid_at' => $this->paid_at,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'sku' => $item->sku,
                'options' => $item->options,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'tax_cents' => $item->tax_cents,
                'total_cents' => $item->total_cents,
            ])),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'note' => $h->note,
                'user' => $h->user?->name,
                'created_at' => $h->created_at,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'provider' => $p->provider,
                'provider_ref' => $p->provider_ref,
                'amount_cents' => $p->amount_cents,
                'status' => $p->status,
                'paid_at' => $p->paid_at,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
