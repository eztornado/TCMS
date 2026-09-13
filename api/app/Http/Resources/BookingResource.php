<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'slug' => $this->event->slug,
            ]),
            'session' => $this->whenLoaded('session', fn () => $this->session ? [
                'id' => $this->session->id,
                'title' => $this->session->title,
                'starts_at' => $this->session->starts_at,
            ] : null),
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'seats' => $this->seats,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->number),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
