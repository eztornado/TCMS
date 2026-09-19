<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Event;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Models\SyncOutbox;
use App\Models\User;
use App\Services\Sync\PullService;
use App\Services\Sync\PushService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncPushTest extends TestCase
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
        Event::factory()->create();

        $admin = User::query()->where('email', 'admin@example.com')->first();
        $this->device = $admin->devices()->create(['label' => 'Portátil de test']);

        // Estado local del device = primer pull completo.
        app(PullService::class)->pull($this->device);
    }

    /** Cambia al runtime nativo: los cambios locales van al outbox. */
    private function goNative(): void
    {
        config(['tcms.runtime' => 'native-desktop']);
    }

    public function test_cambios_locales_del_device_se_envian_al_central(): void
    {
        $this->goNative();

        Product::query()->first()->update(['title' => 'Editado en el device']);

        $this->assertSame(1, SyncOutbox::query()->count());

        // Simulación de dos bases: el central no ha cambiado desde el pull
        // (su fila es más antigua que la edición local del device).
        Product::query()->first()->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

        $summary = app(PushService::class)->sync($this->device->fresh());

        $this->assertSame(1, $summary['pushed']);
        $this->assertSame(1, $summary['applied']);
        $this->assertSame(0, SyncOutbox::query()->count());

        // Publicado en el log con origen: los otros devices lo descargarán,
        // el autor no (no es eco).
        $log = SyncLog::query()
            ->where('table_name', 'products')
            ->where('origin_device_uuid', $this->device->uuid)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('Editado en el device', $log->payload['title']);
    }

    public function test_el_push_de_un_borrado_deja_tombstone_con_origen(): void
    {
        $this->goNative();

        $event = Event::query()->first();
        $event->delete();

        // Simulación de dos bases: la fila "central" sigue existiendo hasta
        // que llegue el push (el delete local solo afectó a la copia local).
        DB::table('events')->insert(collect($event->getAttributes())
            ->merge(['uuid' => $event->uuid])
            ->forget('id')
            ->all());

        $summary = app(PushService::class)->sync($this->device->fresh());

        $this->assertSame(1, $summary['applied']);
        $this->assertDatabaseMissing('events', ['uuid' => $event->uuid]);
        $this->assertDatabaseHas('sync_tombstones', [
            'table_name' => 'events',
            'uuid' => $event->uuid,
            'origin_device_uuid' => $this->device->uuid,
        ]);
    }

    public function test_push_rechaza_cambios_en_tablas_pull_only(): void
    {
        $this->goNative();

        Setting::query()->first()->update(['value' => 'cambiado-local']);

        $summary = app(PushService::class)->sync($this->device->fresh());

        $this->assertSame(1, $summary['rejected']);
        $this->assertSame(0, $summary['applied']);
        $this->assertSame(0, SyncOutbox::query()->count());
    }

    public function test_sin_outbox_el_push_no_hace_nada_pero_pull_sincroniza(): void
    {
        $summary = app(PushService::class)->sync($this->device->fresh());

        $this->assertSame(0, $summary['pushed']);
        $this->assertGreaterThan(0, $summary['cursor']);
    }
}
