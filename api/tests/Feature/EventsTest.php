<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eventos de ocio en el core: sesiones con aforo propio y control de plazas
 * en las reservas (en rotary solo existía un catálogo con enlace de pago
 * externo, sin aforo ni inscripciones).
 */
class EventsTest extends TestCase
{
    use RefreshDatabase;

    private function createEventWithSession(int $capacity = 2): Event
    {
        $event = Event::create([
            'slug' => 'taller-vermut',
            'title' => 'Taller de vermut',
            'status' => 'published',
            'capacity' => $capacity,
            'price_cents' => 1500,
            'currency' => 'EUR',
        ]);

        $event->sessions()->create([
            'title' => 'Pase de mañana',
            'starts_at' => now()->addWeek(),
            'capacity' => $capacity,
        ]);

        return $event->fresh();
    }

    public function test_admin_crea_evento_con_sesiones(): void
    {
        $this->seedCore();
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson('/api/admin/events', [
                'title' => 'Ruta guiada',
                'slug' => 'ruta-guiada',
                'status' => 'published',
                'capacity' => 20,
                'sessions' => [
                    ['starts_at' => now()->addDays(7)->toDateTimeString(), 'capacity' => 20],
                ],
            ])
            ->assertCreated();

        $event = Event::query()->where('slug', 'ruta-guiada')->first();
        $this->assertNotNull($event);
        $this->assertCount(1, $event->sessions);
    }

    public function test_listado_publico_solo_muestra_publicados(): void
    {
        $visible = $this->createEventWithSession();

        Event::create([
            'slug' => 'borrador',
            'title' => 'Evento oculto',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/store/events')->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertContains($visible->slug, $slugs);
        $this->assertNotContains('borrador', $slugs);
    }

    public function test_reserva_descuenta_plazas_y_agota_la_sesion(): void
    {
        $event = $this->createEventWithSession(capacity: 2);
        $session = $event->sessions->first();

        $this->postJson('/api/store/bookings', [
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'seats' => 2,
        ])->assertCreated();

        // Aforo agotado: una reserva más debe rechazarse con 409.
        $this->postJson('/api/store/bookings', [
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'customer_name' => 'Luis',
            'customer_email' => 'luis@example.com',
            'seats' => 1,
        ])->assertStatus(409);

        $this->assertSame(2, (int) $event->bookings()->sum('seats'));
    }

    public function test_cancelar_una_reserva_libera_plazas(): void
    {
        $event = $this->createEventWithSession(capacity: 2);
        $session = $event->sessions->first();

        $booking = Booking::create([
            'reference' => 'EV-TEST-0001',
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'seats' => 2,
            'status' => 'confirmed',
            'amount_cents' => 3000,
            'currency' => 'EUR',
        ]);

        $this->seedCore();
        $admin = $this->adminUser();
        $this->actingAs($admin)
            ->putJson("/api/admin/bookings/{$booking->id}", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(2, $event->fresh()->seatsLeft($session->fresh()));
    }

    public function test_reserva_sobre_sesion_cerrada_se_rechaza(): void
    {
        $event = Event::create([
            'slug' => 'cena-gala',
            'title' => 'Cena de gala',
            'status' => 'published',
            'capacity' => 100,
        ]);

        $session = $event->sessions()->create([
            'starts_at' => now()->addWeek(),
            'sale_ends_at' => now()->subDay(),
        ]);

        $this->postJson('/api/store/bookings', [
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'seats' => 1,
        ])->assertStatus(422);
    }

    public function test_plazas_libres_del_evento_son_visibles(): void
    {
        $event = $this->createEventWithSession(capacity: 4);

        $this->getJson("/api/store/events/{$event->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $event->slug);
    }
}
