<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Auditoría de acciones: los modelos de dominio registran created/updated/
 * deleted con usuario, diff old/new y metadatos; el endpoint permite filtrar.
 */
class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_actualizar_un_usuario_registra_actividad_con_diff(): void
    {
        $this->seedCore();
        $admin = $this->adminUser();
        $target = User::factory()->create(['name' => 'Original']);

        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$target->id}", [
                'name' => 'Renombrado',
                'email' => $target->email,
                'is_active' => true,
            ])
            ->assertOk();

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $target->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'La actualización debería quedar auditada.');
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame('Original', $activity->properties['old']['name'] ?? null);
        $this->assertSame('Renombrado', $activity->properties['attributes']['name'] ?? null);
    }

    public function test_el_endpoint_de_auditoria_lista_y_filtra_por_evento(): void
    {
        $this->seedCore();
        $admin = $this->adminUser();
        $target = User::factory()->create();
        $target->update(['name' => 'Otro nombre']);

        $this->actingAs($admin)->getJson('/api/admin/audit')->assertOk();

        $filtered = $this->actingAs($admin)
            ->getJson('/api/admin/audit?event=updated')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($filtered);
        foreach ($filtered as $entry) {
            $this->assertSame('updated', $entry['event']);
        }
    }

    public function test_borrados_quedan_registrados(): void
    {
        $this->seedCore();
        $admin = $this->adminUser();
        $target = User::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/admin/users/{$target->id}")->assertOk();

        $this->assertTrue(
            Activity::query()
                ->where('subject_type', User::class)
                ->where('subject_id', $target->id)
                ->where('event', 'deleted')
                ->exists(),
        );
    }
}
