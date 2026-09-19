<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Product;
use App\Models\SyncOutbox;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoints LOCALES que consume el indicador de sync del panel
 * (front/src/features/sync): GET /api/sync/state y POST /api/sync/run.
 * En el central (runtime web) no hay nada que sincronizar y el run da 422.
 */
class SyncStateEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->admin = User::query()->where('email', 'admin@example.com')->first();
    }

    public function test_el_estado_sin_sesion_da_401(): void
    {
        $this->getJson('/api/sync/state')->assertUnauthorized();
        $this->postJson('/api/sync/run')->assertUnauthorized();
    }

    public function test_en_web_el_estado_indica_que_la_sync_no_aplica(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/sync/state')
            ->assertOk()
            ->assertJsonPath('data.runtime', 'web')
            ->assertJsonPath('data.available', false);
    }

    public function test_en_web_el_run_da_422(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/sync/run')
            ->assertUnprocessable();
    }

    public function test_en_nativo_el_estado_y_el_run_reflejan_el_outbox(): void
    {
        // El device local existe y está enlazado (web: catálogo ya capturado).
        config(['tcms.runtime' => 'web']);
        $this->admin->devices()->create([
            'uuid' => '33333333-4444-5555-6666-777777777777',
            'label' => 'Portátil nativo',
        ]);
        Product::factory()->create();

        config([
            'tcms.runtime' => 'native-desktop',
            'tcms.sync.device_uuid' => '33333333-4444-5555-6666-777777777777',
            'tcms.sync.device_label' => 'Portátil nativo',
        ]);

        // Cambio local "offline": aparece como pendiente.
        Product::query()->first()->update(['title' => 'offline']);
        $this->assertSame(1, SyncOutbox::query()->count());

        // Simulación de dos bases: el central no ha cambiado desde el pull.
        Product::query()->first()->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

        $this->actingAs($this->admin)
            ->getJson('/api/sync/state')
            ->assertOk()
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.available', true);

        // El run del device vacía el outbox y devuelve el resumen.
        $this->actingAs($this->admin)
            ->postJson('/api/sync/run')
            ->assertOk()
            ->assertJsonPath('data.pushed', 1)
            ->assertJsonPath('data.applied', 1);

        $this->assertSame(0, SyncOutbox::query()->count());

        // El estado vuelve a cero pendientes y guarda el último run.
        $this->actingAs($this->admin)
            ->getJson('/api/sync/state')
            ->assertOk()
            ->assertJsonPath('data.pending', 0);
    }
}
