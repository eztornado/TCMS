<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Product;
use App\Models\User;
use App\Services\Sync\PullService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Los comandos artisan son la cara operativa del sync (cron del binario,
 * operación manual). El device local se crea/completa desde la config.
 */
class SyncCommandsTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->first();
        $this->device = $admin->devices()->create(['label' => 'Portátil de test']);

        Product::factory()->create();

        config([
            'tcms.sync.device_uuid' => $this->device->uuid,
            'tcms.sync.token' => 'token-de-prueba',
        ]);
    }

    public function test_sync_run_aplica_los_deltas_y_avanza_el_cursor(): void
    {
        $this->artisan('sync:run')->assertSuccessful();

        $this->assertSame(1, DB::table('products')->count());
        $this->assertGreaterThan(0, $this->device->fresh()->last_pull_cursor);
        $this->assertNotNull($this->device->fresh()->last_synced_at);
    }

    public function test_sync_run_crea_la_fila_local_del_device_si_no_existe(): void
    {
        // Contexto de device sin fila local (instalación recién enlazada):
        // se crea con el uuid de la config.
        $uuid = '22222222-3333-4444-5555-666666666666';
        config(['tcms.sync.device_uuid' => $uuid, 'tcms.sync.device_label' => 'Móvil nuevo']);

        $this->artisan('sync:run')->assertSuccessful();

        $this->assertDatabaseHas('devices', ['uuid' => $uuid, 'label' => 'Móvil nuevo']);
    }

    public function test_sync_run_sin_device_identificado_falla(): void
    {
        config(['tcms.sync.device_uuid' => null]);

        $this->expectException(RuntimeException::class);
        $this->artisan('sync:run');
    }

    public function test_sync_status_muestra_el_estado_o_falla_si_no_hay_device(): void
    {
        $this->artisan('sync:status')->assertSuccessful();

        config(['tcms.sync.device_uuid' => null]);
        $this->artisan('sync:status')->assertFailed();
    }

    public function test_sync_resync_repite_todo_el_log_desde_cero(): void
    {
        // Primer sync: el device ya está al día.
        app(PullService::class)->pull($this->device);
        $cursor = $this->device->fresh()->last_pull_cursor;
        $this->assertGreaterThan(0, $cursor);

        $this->artisan('sync:resync')->assertSuccessful();

        // El cursor vuelve a avanzar hasta el mismo sitio (todo re-aplicado).
        $this->assertSame($cursor, $this->device->fresh()->last_pull_cursor);
    }
}
