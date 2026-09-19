<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Product;
use App\Models\SyncConflict;
use App\Models\SyncOutbox;
use App\Models\User;
use App\Services\Sync\ConflictResolver;
use App\Services\Sync\PullService;
use App\Services\Sync\PushService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncConflictTest extends TestCase
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

        Product::factory()->create();

        $admin = User::query()->where('email', 'admin@example.com')->first();
        $this->device = $admin->devices()->create(['label' => 'Portátil de test']);

        app(PullService::class)->pull($this->device);
    }

    public function test_conflicto_lww_gana_el_central_y_el_pull_converge(): void
    {
        // 1. El device edita offline (su copia local queda "adelantada").
        config(['tcms.runtime' => 'native-desktop']);
        Product::query()->first()->update(['title' => 'Versión del device']);

        // 2. El central edita DESPUÉS (web): updated_at más reciente que el
        //    cambio del device, así que el LWW debe darle la razón al central.
        config(['tcms.runtime' => 'web']);
        Product::query()->first()->update(['title' => 'Versión del central']);

        $this->assertSame(1, SyncOutbox::query()->count());

        // 3. Push: el cambio del device pierde contra el central.
        $summary = app(PushService::class)->sync($this->device->fresh());

        $this->assertSame(1, $summary['conflicts']);
        $this->assertSame(0, $summary['applied']);

        // El choque queda registrado con la versión central para auditoría.
        $conflict = SyncConflict::query()->first();
        $this->assertNotNull($conflict);
        $this->assertSame('products', $conflict->table_name);
        $this->assertSame('Versión del central', $conflict->central_payload['title']);

        // El outbox se limpia: el device no insistirá en su versión.
        $this->assertSame(0, SyncOutbox::query()->count());

        // 4. El pull converge: el central manda.
        $this->assertSame(
            'Versión del central',
            Product::query()->first()->title,
        );
    }

    public function test_cambio_del_device_mas_reciente_que_el_central_se_aplica(): void
    {
        config(['tcms.runtime' => 'native-desktop']);
        Product::query()->first()->update(['title' => 'Versión del device']);

        // El central se quedó atrás (simulado: su fila es más antigua).
        Product::query()->first()->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

        $summary = app(PushService::class)->sync($this->device->fresh());

        $this->assertSame(1, $summary['applied']);
        $this->assertSame(0, SyncConflict::query()->count());
        $this->assertSame('Versión del device', Product::query()->first()->title);
    }

    public function test_el_resolver_decide_por_orden_lexico_de_timestamps(): void
    {
        $resolver = new ConflictResolver;

        $this->assertTrue($resolver->deviceChangeWins('2026-09-18 10:00:00', '2026-09-18 09:00:00'));
        $this->assertFalse($resolver->deviceChangeWins('2026-09-18 09:00:00', '2026-09-18 10:00:00'));
        // Empate: gana el central (determinista).
        $this->assertFalse($resolver->deviceChangeWins('2026-09-18 10:00:00', '2026-09-18 10:00:00'));
        // Fila nueva en central: siempre entra.
        $this->assertTrue($resolver->deviceChangeWins('2026-09-18 10:00:00', null));
    }
}
