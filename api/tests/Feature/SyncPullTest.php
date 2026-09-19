<?php

namespace Tests\Feature;

use App\Models\CustomModel;
use App\Models\Device;
use App\Models\DynamicEntry;
use App\Models\Event;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\CustomModels\SchemaService;
use App\Services\Sync\PullService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyncPullTest extends TestCase
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
    }

    public function test_pull_inicial_replica_usuarios_roles_configuracion_y_pivots(): void
    {
        $response = app(PullService::class)->pull($this->device);

        $this->assertGreaterThan(0, $response['cursor']);
        $this->assertFalse($response['has_more']);

        // El usuario y su rol existen localmente con el mismo uuid.
        $centralUser = User::query()->where('email', 'admin@example.com')->first();
        $this->assertNotNull($centralUser->uuid);
        $this->assertSame(1, DB::table('users')->count());

        $this->assertTrue(Role::query()->where('name', 'Admin')->exists());
        $this->assertNotNull(DB::table('settings')->count());

        // Pivots: el admin conserva su rol tras el snapshot.
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $centralUser->id,
            'role_id' => Role::query()->where('name', 'Admin')->value('id'),
        ]);

        // El pull no se re-captura: el log no crece al aplicar.
        $logAfter = SyncLog::query()->count();
        app(PullService::class)->pull($this->device->fresh());
        $this->assertSame($logAfter, SyncLog::query()->count());
    }

    public function test_segundo_pull_solo_trae_el_delta(): void
    {
        app(PullService::class)->pull($this->device);
        $cursor = $this->device->fresh()->last_pull_cursor;

        $product = Product::factory()->create();

        $response = app(PullService::class)->pull($this->device->fresh());

        $tables = collect($response['changes'])->pluck('table')->unique()->values();
        $this->assertSame(['products'], $tables->all());
        $this->assertGreaterThan($cursor, $response['cursor']);

        $this->assertDatabaseHas('products', [
            'uuid' => $product->uuid,
            'slug' => $product->slug,
        ]);
    }

    public function test_aplica_tabla_dinamica_materializando_el_esquema_local(): void
    {
        $customModel = CustomModel::create([
            'slug' => 'recetas',
            'label' => 'Receta',
            'plural_label' => 'Recetas',
            'table_name' => SchemaService::makeTableName('recetas'),
            'is_taxonomizable' => false,
            'created_by' => User::query()->value('id'),
        ]);
        $customModel->fields()->create([
            'name' => 'tiempo_minutos',
            'label' => 'Tiempo (min)',
            'type' => 'number',
        ]);
        app(SchemaService::class)->createTable($customModel);

        $entry = DynamicEntry::for($customModel->fresh());
        $entry->forceFill(['tiempo_minutos' => 45, 'status' => 'published']);
        $entry->save();

        $this->device->update(['last_pull_cursor' => 0]);
        app(PullService::class)->pull($this->device->fresh());

        $this->assertTrue(Schema::hasTable('cm_recetas'));
        $this->assertTrue(Schema::hasColumn('cm_recetas', 'uuid'));
        $this->assertTrue(Schema::hasColumn('cm_recetas', 'tiempo_minutos'));

        $this->assertDatabaseHas('cm_recetas', [
            'uuid' => $entry->uuid,
            'tiempo_minutos' => 45,
        ]);
    }

    public function test_borrado_en_central_elimina_la_fila_y_deja_tombstone(): void
    {
        $event = Event::factory()->create();
        app(PullService::class)->pull($this->device);

        $this->assertDatabaseHas('events', ['uuid' => $event->uuid]);

        $event->delete();

        $response = app(PullService::class)->pull($this->device->fresh());

        $this->assertContains(
            'events',
            collect($response['changes'])->where('op', 'delete')->pluck('table'),
        );
        $this->assertDatabaseMissing('events', ['uuid' => $event->uuid]);
        $this->assertDatabaseHas('sync_tombstones', [
            'table_name' => 'events',
            'uuid' => $event->uuid,
        ]);
    }

    public function test_los_cambios_del_propio_device_no_se_le_devuelven(): void
    {
        app(PullService::class)->pull($this->device);
        $cursor = $this->device->fresh()->last_pull_cursor;

        SyncLog::create([
            'table_name' => 'settings',
            'uuid' => (string) Str::uuid(),
            'op' => 'upsert',
            'payload' => ['key' => 'eco', 'value' => 'propio'],
            'origin_device_uuid' => $this->device->uuid,
        ]);

        $response = app(PullService::class)->pull($this->device->fresh());

        $this->assertEmpty($response['changes']);
        // El cursor avanza igualmente (no re-descargará nunca ese cambio).
        $this->assertGreaterThan($cursor, $response['cursor']);
    }

    public function test_endpoint_http_de_pull_con_token_de_device(): void
    {
        $login = $this->postJson('/api/auth/device/login', [
            'email' => 'admin@example.com',
            'password' => 'cambia-esto-ya',
            'label' => 'Otro portátil',
        ]);

        $login->assertCreated();
        $token = $login->json('token');

        $this->withToken($token)
            ->postJson('/api/sync/pull')
            ->assertOk()
            ->assertJsonStructure(['changes', 'pivots', 'cursor', 'has_more']);

        $this->withToken($token)
            ->getJson('/api/sync/manifest')
            ->assertOk()
            ->assertJsonStructure(['version', 'tables', 'pivots']);

        // Sin token no hay sync. En tests hacen falta dos cosas: withoutToken
        // (withToken persiste la cabecera) y auth fresco (el RequestGuard de
        // sanctum memoiza al usuario resuelto entre peticiones del test).
        $this->refreshAuth();
        $this->withoutToken()->postJson('/api/sync/pull')->assertUnauthorized();
    }

    public function test_un_device_con_limite_recibe_paginacion_de_deltas(): void
    {
        // 510 deltas falsos: superan el límite por página del endpoint (500).
        $rows = [];
        foreach (range(1, 510) as $i) {
            $rows[] = [
                'table_name' => 'settings',
                'uuid' => (string) Str::uuid(),
                'op' => 'upsert',
                'payload' => json_encode(['key' => "fake-$i", 'value' => $i]),
                'origin_device_uuid' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('sync_log')->insert($rows);

        $response = app(PullService::class)->pull($this->device);

        $this->assertTrue($response['has_more']);
        $this->assertCount(500, $response['changes']);

        // Continúa hasta drenar.
        while ($response['has_more']) {
            $response = app(PullService::class)->pull($this->device->fresh());
        }

        $this->assertFalse($response['has_more']);
    }
}
