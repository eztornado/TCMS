<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $bookings = Booking::query()
            ->with(['event:id,title,slug', 'session:id,title,starts_at', 'order:id,number'])
            ->when($request->q, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")))
            ->when($request->event_id, fn ($q, $id) => $q->where('event_id', $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->payment_status, fn ($q, $ps) => $q->where('payment_status', $ps))
            ->sortableBy(['id', 'reference', 'created_at'], $request)
            ->paginate($request->integer('per_page', 25));

        return BookingResource::collection($bookings);
    }

    public function update(Request $request, Booking $booking): BookingResource
    {
        $data = $request->validate([
            'status' => [Rule::enum(BookingStatus::class)],
            'payment_status' => ['in:unpaid,paid,refunded'],
            'notes' => ['nullable', 'string'],
            'seats' => ['integer', 'min:1'],
        ]);

        $booking->update($data);

        return new BookingResource($booking->load(['event:id,title,slug', 'session:id,title,starts_at']));
    }

    public function destroy(Booking $booking): JsonResponse
    {
        // Una cancelación libera las plazas; el borrado queda auditado.
        if ($booking->status !== BookingStatus::Cancelled) {
            $booking->update(['status' => BookingStatus::Cancelled]);

            return response()->json(['message' => 'Reserva cancelada.']);
        }

        $booking->delete();

        return response()->json(['message' => 'Reserva eliminada.']);
    }
}
