<?php

namespace App\Http\Controllers\Store;

use App\Enums\BookingStatus;
use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Inscripciones públicas con control real de aforo (en rotary no existía
 * inscripción: solo una stripe_url externa sin comprobar plazas).
 */
class BookingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer'],
            'event_session_id' => ['nullable', 'integer'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'seats' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $event = Event::published()->findOrFail($data['event_id']);
        $session = $data['event_session_id']
            ? EventSession::where('event_id', $event->id)->findOrFail($data['event_session_id'])
            : null;

        if ($session && ! $session->isOnSale()) {
            throw new AppException(
                AppException::EVENT_BOOKING_CLOSED,
                'Las inscripciones para esta sesión no están abiertas.',
                422,
            );
        }

        $seatsLeft = $event->seatsLeft($session);

        if ($seatsLeft !== null && $seatsLeft < $data['seats']) {
            throw new AppException(
                AppException::EVENT_SOLD_OUT,
                "Solo quedan {$seatsLeft} plazas.",
                409,
            );
        }

        $priceCents = $event->effectivePriceCents($session);

        $booking = Booking::create([
            'reference' => 'EV-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
            'event_id' => $event->id,
            'event_session_id' => $session?->id,
            'user_id' => $request->user()?->id,
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'seats' => $data['seats'],
            'amount_cents' => ($priceCents ?? 0) * $data['seats'],
            'currency' => $event->currency,
            'status' => BookingStatus::Pending,
            'payment_status' => ($priceCents ?? 0) > 0 ? 'unpaid' : 'paid',
        ]);

        return response()->json(['data' => new BookingResource($booking->load('event')), 'message' => 'Reserva registrada.'], 201);
    }
}
