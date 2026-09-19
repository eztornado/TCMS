<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_de_device_emite_token_con_ability_sync(): void
    {
        $this->seedCore();
        $this->seed(AdminUserSeeder::class);

        $response = $this->postJson('/api/auth/device/login', [
            'email' => 'admin@example.com',
            'password' => 'cambia-esto-ya',
            'label' => 'Portátil de Ana',
            'platform' => 'desktop',
        ]);

        $response->assertCreated()
            ->assertJsonPath('device.label', 'Portátil de Ana')
            ->assertJsonStructure(['token']);

        $device = Device::query()->sole();
        $this->assertNotNull($device->uuid);

        // El token autentica contra rutas sanctum con ability `sync`.
        $this->withToken($response->json('token'))
            ->getJson('/api/auth/devices')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $device->uuid);
    }

    public function test_alta_de_device_desde_sesion_web(): void
    {
        $this->seedCore();
        $this->seed(AdminUserSeeder::class);
        $admin = User::query()->where('email', 'admin@example.com')->first();

        $response = $this->actingAs($admin)->postJson('/api/auth/device', [
            'label' => 'Tableta de recepción',
            'platform' => 'android',
        ]);

        $response->assertCreated()->assertJsonPath('device.platform', 'android');
        $this->assertSame(1, $admin->devices()->count());
    }

    public function test_login_de_device_rechaza_credenciales_incorrectas(): void
    {
        $this->seedCore();
        $this->seed(AdminUserSeeder::class);

        $this->postJson('/api/auth/device/login', [
            'email' => 'admin@example.com',
            'password' => 'incorrecta',
            'label' => 'X',
        ])->assertUnprocessable();

        $this->assertSame(0, Device::query()->count());
    }

    public function test_revocar_device_invalida_su_token(): void
    {
        $this->seedCore();
        $this->seed(AdminUserSeeder::class);

        $token = $this->postJson('/api/auth/device/login', [
            'email' => 'admin@example.com',
            'password' => 'cambia-esto-ya',
            'label' => 'Móvil de Pedro',
        ])->json('token');

        $deviceId = Device::query()->sole()->id;

        $this->withToken($token)
            ->deleteJson("/api/auth/devices/{$deviceId}")
            ->assertOk();

        $this->assertSame(0, User::query()->first()->tokens()->count());
        $this->assertNotNull(Device::query()->sole()->revoked_at);

        // Auth fresco: sin esto, el guard sanctum devolvería al usuario
        // memoizado de la petición anterior (ver refreshAuth()).
        $this->refreshAuth();

        $this->withToken($token)
            ->getJson('/api/auth/devices')
            ->assertUnauthorized();
    }

    public function test_no_se_puede_revocar_un_device_ajeno(): void
    {
        $this->seedCore();
        $this->seed(AdminUserSeeder::class);

        $otro = User::create([
            'name' => 'Otro',
            'email' => 'otro@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $deviceDeOtro = $otro->devices()->create(['label' => 'Device ajeno']);

        $token = $this->postJson('/api/auth/device/login', [
            'email' => 'admin@example.com',
            'password' => 'cambia-esto-ya',
            'label' => 'Admin device',
        ])->json('token');

        $this->withToken($token)
            ->deleteJson("/api/auth/devices/{$deviceDeOtro->id}")
            ->assertNotFound();

        $this->assertNull($deviceDeOtro->fresh()->revoked_at);
    }
}
