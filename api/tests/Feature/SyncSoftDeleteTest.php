<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncTombstone;
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

/**
 * Soft deletes: la fila SIGUE existiendo (con deleted_at) en el central, así
 * que viaja como upsert, no como delete. Solo el forceDelete es un delete
 * real con tombstone. Así el device replica el estado exacto del central.
 */
class SyncSoftDeleteTest extends TestCase
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

        Product::factory()->has(ProductVariant::factory()->count(1), 'variants')->create();
    }

    public function test_un_soft_delete_central_viaja_como_upsert_con_deleted_at(): void
    {
        app(PullService::class)->pull($this->device);

        $variant = ProductVariant::query()->first();
        $variant->delete(); // soft delete: sync_log op=upsert

        $response = app(PullService::class)->pull($this->device->fresh());

        $this->assertSame(
            ['upsert'],
            collect($response['changes'])->where('table', 'product_variants')->pluck('op')->unique()->all(),
        );

        // La fila existe localmente con su deleted_at (estado idéntico al central).
        $local = DB::table('product_variants')->where('uuid', $variant->uuid)->first();
        $this->assertNotNull($local);
        $this->assertNotNull($local->deleted_at);
        $this->assertNull(SyncTombstone::query()->where('uuid', $variant->uuid)->first());
    }

    public function test_la_restauracion_viaja_como_upsert_limpio(): void
    {
        app(PullService::class)->pull($this->device);

        $variant = ProductVariant::query()->first();
        $variant->delete();
        app(PullService::class)->pull($this->device->fresh());

        $variant->restore();
        $response = app(PullService::class)->pull($this->device->fresh());

        $this->assertContains(
            'product_variants',
            collect($response['changes'])->where('op', 'upsert')->pluck('table'),
        );

        $local = DB::table('product_variants')->where('uuid', $variant->uuid)->first();
        $this->assertNull($local->deleted_at);
    }

    public function test_el_force_delete_es_un_delete_real_con_tombstone(): void
    {
        app(PullService::class)->pull($this->device);

        $variant = ProductVariant::query()->first();
        $variant->forceDelete();

        $response = app(PullService::class)->pull($this->device->fresh());

        $this->assertContains(
            'product_variants',
            collect($response['changes'])->where('op', 'delete')->pluck('table'),
        );
        $this->assertDatabaseMissing('product_variants', ['uuid' => $variant->uuid]);
        $this->assertDatabaseHas('sync_tombstones', [
            'table_name' => 'product_variants',
            'uuid' => $variant->uuid,
        ]);
    }

    public function test_el_push_del_device_de_un_borrado_logico_deja_deleted_at_en_el_central(): void
    {
        app(PullService::class)->pull($this->device);

        config(['tcms.runtime' => 'native-desktop']);

        $variant = ProductVariant::query()->first();
        $variant->delete();

        // El central "no ha cambiado": su fila queda atrás para ganar el LWW.
        $variant->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

        $summary = app(PushService::class)->sync($this->device->fresh());

        $this->assertSame(1, $summary['applied']);

        $central = DB::table('product_variants')->where('uuid', $variant->uuid)->first();
        $this->assertNotNull($central);
        $this->assertNotNull($central->deleted_at);
    }
}
