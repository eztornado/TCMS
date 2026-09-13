<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\Shop\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = Event::query()
            ->with(['cover', 'sessions'])
            ->withCount('bookings')
            ->when($request->q, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->sortableBy(['id', 'title', 'starts_at', 'created_at'], $request)
            ->paginate($request->integer('per_page', 25));

        return EventResource::collection($events);
    }

    public function store(Request $request): EventResource
    {
        $data = $this->validated($request);
        $data['slug'] ??= SlugService::from($data['title'], 'events');
        $data['user_id'] = auth()->id();

        $event = Event::create($data);
        $this->syncSessions($event, $request->input('sessions', []));
        $this->syncCover($event, $request->integer('cover_media_id'));

        return new EventResource($event->load(['cover', 'sessions']));
    }

    public function show(Event $event): EventResource
    {
        return new EventResource($event->load(['cover', 'sessions'])->loadCount('bookings'));
    }

    public function update(Request $request, Event $event): EventResource
    {
        $data = $this->validated($request, $event);

        if ($data['status'] ?? null) {
            $data['published_at'] = $data['status'] === 'published' ? ($event->published_at ?? now()) : null;
        }

        $event->update($data);
        $this->syncSessions($event, $request->input('sessions', []));
        $this->syncCover($event, $request->integer('cover_media_id'));

        return new EventResource($event->refresh()->load(['cover', 'sessions']));
    }

    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return response()->json(['message' => 'Evento eliminado.']);
    }

    protected function validated(Request $request, ?Event $existing = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255',
                Rule::unique('events', 'slug')->ignore($existing?->id)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'status' => [Rule::in(['draft', 'published', 'archived'])],
            'venue' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['string', 'size:3'],
            'is_featured' => ['boolean'],
            'cover_media_id' => ['nullable', 'integer'],
            'sessions' => ['array'],
            'sessions.*.title' => ['nullable', 'string', 'max:255'],
            'sessions.*.starts_at' => ['required', 'date'],
            'sessions.*.ends_at' => ['nullable', 'date', 'after:sessions.*.starts_at'],
            'sessions.*.capacity' => ['nullable', 'integer', 'min:1'],
            'sessions.*.price_cents' => ['nullable', 'integer', 'min:0'],
            'sessions.*.sale_starts_at' => ['nullable', 'date'],
            'sessions.*.sale_ends_at' => ['nullable', 'date'],
        ]);

        unset($data['sessions'], $data['cover_media_id']);

        return $data;
    }

    /** Reemplaza las sesiones del evento (borrado y recreación atómica). */
    protected function syncSessions(Event $event, array $sessions): void
    {
        if ($sessions === []) {
            return;
        }

        DB::transaction(function () use ($event, $sessions) {
            $event->sessions()->delete();

            foreach (array_values($sessions) as $index => $session) {
                $event->sessions()->create([...$session, 'sort' => $index]);
            }
        });
    }

    protected function syncCover(Event $event, ?int $mediaId): void
    {
        if (! $mediaId) {
            return;
        }

        $event->cover()->sync([$mediaId => ['collection_name' => 'cover', 'sort' => 0]]);
    }
}
