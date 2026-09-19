<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Canal HTTP real de ficheros de media (central): check/subida/descarga con
 * token de device. El loopback de SyncMediaTest valida la lógica; esto
 * valida el transporte.
 */
class SyncMediaHttpTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(AdminUserSeeder::class);

        Storage::fake('public');

        $login = $this->postJson('/api/auth/device/login', [
            'email' => 'admin@example.com',
            'password' => 'cambia-esto-ya',
            'label' => 'Portátil de test',
        ]);
        $this->token = $login->json('token');
        $this->device = Device::query()->sole();
    }

    private function crearMedia(): Media
    {
        return app(MediaService::class)->store(
            UploadedFile::fake()->image('central.jpg', 800, 600),
            User::query()->value('id'),
        );
    }

    public function test_check_devuelve_los_ficheros_que_faltan_en_el_central(): void
    {
        $media = $this->crearMedia();

        // El fichero está: el central no pide nada.
        $this->withToken($this->token)
            ->postJson('/api/sync/media/check', [
                'files' => [['uuid' => $media->uuid, 'hash' => $media->hash]],
            ])
            ->assertOk()
            ->assertJson(['missing' => []]);

        // Sin fichero (device re-subiendo): lo pide.
        Storage::disk('public')->delete($media->path);
        $this->withToken($this->token)
            ->postJson('/api/sync/media/check', [
                'files' => [['uuid' => $media->uuid, 'hash' => $media->hash]],
            ])
            ->assertOk()
            ->assertJson(['missing' => [$media->uuid]]);
    }

    public function test_subida_http_de_bytes_actualiza_hash_y_miniatura(): void
    {
        $media = $this->crearMedia();
        Storage::disk('public')->delete([$media->path, $media->thumbnail_path]);

        $this->withToken($this->token)
            ->post("/api/sync/media/{$media->uuid}", [
                'file' => UploadedFile::fake()->image('device.jpg', 800, 600),
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'media' => ['uuid', 'hash']]);

        Storage::disk('public')->assertExists($media->path);
        $this->assertNotNull($media->fresh()->thumbnail_path);
    }

    public function test_descarga_http_devuelve_los_bytes_del_original(): void
    {
        $media = $this->crearMedia();

        $response = $this->withToken($this->token)
            ->get("/api/sync/media/{$media->uuid}/download");

        $response->assertOk();
        $this->assertSame(
            Storage::disk('public')->get($media->path),
            $response->streamedContent(),
        );
    }

    public function test_sin_token_no_hay_canal_de_media(): void
    {
        $media = $this->crearMedia();

        $this->refreshAuth();

        $this->postJson('/api/sync/media/check', ['files' => []])->assertUnauthorized();
        $this->postJson("/api/sync/media/{$media->uuid}")->assertUnauthorized();
        $this->get("/api/sync/media/{$media->uuid}/download")->assertUnauthorized();
    }

    public function test_subida_para_un_media_desconocido_da_404(): void
    {
        $this->withToken($this->token)
            ->post('/api/sync/media/11111111-2222-3333-4444-555555555555', [
                'file' => UploadedFile::fake()->image('huerfano.jpg'),
            ])
            ->assertNotFound();
    }

    public function test_subida_sin_fichero_da_422(): void
    {
        $media = $this->crearMedia();

        $this->withToken($this->token)
            ->post("/api/sync/media/{$media->uuid}")
            ->assertUnprocessable();
    }
}
