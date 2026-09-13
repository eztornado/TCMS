<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Agenda pública. En rotary el listado ordenaba DESC y mostraba eventos
 * pasados primero; aquí solo publicados, con sesiones futuras y plazas.
 */
class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = Event::query()
            ->published()
            ->upcoming()
            ->with(['cover', 'sessions' => fn ($q) => $q->where('starts_at', '>=', now())->orderBy('starts_at')])
            ->withMin('sessions', 'starts_at')
            ->when($request->q, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->when($request->city, fn ($q, $city) => $q->where('city', $city))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->orderBy('is_featured', 'desc')
            ->orderBy('sessions_min_starts_at', 'asc')
            ->paginate($request->integer('per_page', 12));

        return EventResource::collection($events);
    }

    public function show(Event $event): EventResource
    {
        abort_unless($event->status->value === 'published', 404);

        return new EventResource(
            $event->load(['cover', 'sessions' => fn ($q) => $q->where('starts_at', '>=', now())->orderBy('starts_at')]),
        );
    }
}
