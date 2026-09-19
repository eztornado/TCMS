<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\User;
use App\Support\Sync\SyncPayload;
use App\Support\Sync\SyncRegistry;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El payload es el idioma del protocolo: FKs como uuid al serializar, de
 * vuelta a ids locales al aplicar. Los ids enteros jamás viajan.
 */
class SyncPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(AdminUserSeeder::class);
    }

    public function test_serializar_sustituye_las_fks_por_uuid(): void
    {
        $user = User::query()->where('email', 'admin@example.com')->first();
        $product = Product::factory()->create(['user_id' => $user->id]);

        $payload = SyncPayload::serialize($product, ['user_id' => 'users']);

        $this->assertSame($user->uuid, $payload['user_id']);
        $this->assertArrayNotHasKey('id', $payload);
        $this->assertArrayHasKey('uuid', $payload);
        $this->assertArrayHasKey('updated_at', $payload);
    }

    public function test_deserializar_resuelve_los_uuid_a_ids_locales(): void
    {
        $user = User::query()->where('email', 'admin@example.com')->first();
        $product = Product::factory()->create(['user_id' => $user->id]);

        $wire = SyncPayload::serialize($product, ['user_id' => 'users']);
        unset($wire['id']);

        $row = SyncPayload::deserialize($wire, ['user_id' => 'users']);

        $this->assertSame($user->id, $row['user_id']);
    }

    public function test_las_fks_nulas_viajan_nulas_y_vuelven_nulas(): void
    {
        $product = Product::factory()->create(['user_id' => null, 'tax_rate_id' => null]);

        $refs = ['user_id' => 'users', 'tax_rate_id' => 'tax_rates'];

        $wire = SyncPayload::serialize($product, $refs);

        $this->assertNull($wire['user_id']);
        $this->assertNull(SyncPayload::deserialize($wire, $refs)['user_id']);
    }

    public function test_pivots_serializan_y_deserializan_los_ids(): void
    {
        $user = User::query()->where('email', 'admin@example.com')->first();
        $role = $user->roles->first();

        $row = (array) DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->first();

        $pivot = SyncRegistry::pivots()['model_has_roles'];

        $wire = SyncPayload::serializePivotRow($row, $pivot);

        $this->assertSame($user->uuid, $wire['model_id']);
        $this->assertSame($role->uuid, $wire['role_id']);
        $this->assertSame(User::class, $wire['model_type']);

        $local = SyncPayload::deserializePivotRow($wire, $pivot);

        $this->assertSame($user->id, $local['model_id']);
        $this->assertSame($role->id, $local['role_id']);
    }
}
