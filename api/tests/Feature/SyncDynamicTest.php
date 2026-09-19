<?php

namespace Tests\Feature;

use App\Models\CustomModel;
use App\Models\Device;
use App\Models\DynamicEntry;
use App\Models\User;
use App\Services\CustomModels\SchemaService;
use App\Services\Sync\PullService;
use App\Services\Sync\PushService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Las tablas dinámicas (cm_*) son la parte más delicada del sync: el device
 * materializa su esquema local a partir de custom_models/fields y luego
 * aplica filas. Bidireccionales: las entradas creadas offline suben al
 * central; los campos nuevos evolucionan el esquema local; borrar la
 * definición en el central destruye la tabla local.
 */
class SyncDynamicTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    private CustomModel $customModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->first();
        $this->device = $admin->devices()->create(['label' => 'Portátil de test']);

        $this->customModel = CustomModel::create([
            'slug' => 'recetas',
            'label' => 'Receta',
            'plural_label' => 'Recetas',
            'table_name' => SchemaService::makeTableName('recetas'),
            'is_taxonomizable' => false,
            'created_by' => $admin->id,
        ]);
        $this->customModel->fields()->create([
            'name' => 'tiempo_minutos',
            'label' => 'Tiempo (min)',
            'type' => 'number',
        ]);
        app(SchemaService::class)->createTable($this->customModel);
    }

    private function entry(int $minutes, string $status = 'published'): DynamicEntry
    {
        $entry = DynamicEntry::for($this->customModel->fresh());
        $entry->forceFill(['status' => $status, 'tiempo_minutos' => $minutes]);
        $entry->save();

        return $entry;
    }

    public function test_una_entrada_creada_offline_sube_al_central(): void
    {
        app(PullService::class)->pull($this->device);

        config(['tcms.runtime' => 'native-desktop']);
        $entry = $this->entry(30);

        // Simulación de dos bases: la fila central es anterior a la edición.
        DB::table('cm_recetas')->where('uuid', $entry->uuid)
            ->update(['updated_at' => now()->subHour()]);

        $summary = app(PushService::class)->sync($this->device->fresh());

        $this->assertSame(1, $summary['applied']);
        $this->assertDatabaseHas('cm_recetas', [
            'uuid' => $entry->uuid,
            'tiempo_minutos' => 30,
        ]);
    }

    public function test_campo_nuevo_en_el_central_evoluciona_el_esquema_local(): void
    {
        // El device sincroniza ANTES de que exista el campo nuevo.
        app(PullService::class)->pull($this->device);
        $this->assertFalse(Schema::hasColumn('cm_recetas', 'dificultad'));

        // Central: campo nuevo + entrada que lo usa.
        $this->customModel->fields()->create([
            'name' => 'dificultad',
            'label' => 'Dificultad',
            'type' => 'select',
            'options' => ['facil', 'media', 'alta'],
        ]);
        app(SchemaService::class)->addColumn(
            $this->customModel->fresh(),
            $this->customModel->fields()->where('name', 'dificultad')->first(),
        );

        $entry = $this->entry(60);
        DB::table('cm_recetas')->where('uuid', $entry->uuid)->update(['dificultad' => 'media']);

        app(PullService::class)->pull($this->device->fresh());

        // El esquema local materializó la columna y la fila llegó completa.
        $this->assertTrue(Schema::hasColumn('cm_recetas', 'dificultad'));
        $this->assertDatabaseHas('cm_recetas', [
            'uuid' => $entry->uuid,
            'dificultad' => 'media',
        ]);
    }

    public function test_borrar_el_custom_model_en_el_central_destruye_la_tabla_local(): void
    {
        app(PullService::class)->pull($this->device);
        $this->assertTrue(Schema::hasTable('cm_recetas'));

        $this->customModel->delete();

        // Simulación de dos bases: la copia local del device aún conserva la
        // definición (el apply la necesita para saber qué tabla soltar).
        DB::table('custom_models')->insert(
            collect($this->customModel->getAttributes())->forget('id')->all(),
        );

        app(PullService::class)->pull($this->device->fresh());

        // La definición desaparece y su tabla física también.
        $this->assertDatabaseMissing('custom_models', ['uuid' => $this->customModel->uuid]);
        $this->assertFalse(Schema::hasTable('cm_recetas'));
    }
}
