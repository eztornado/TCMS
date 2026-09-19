<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Services\Sync\Contracts\SyncGateway;
use App\Services\Sync\MediaFileSyncService;
use App\Services\Sync\MediaSyncEndpoint;
use App\Services\Sync\PushService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncMediaTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->admin = User::query()->where('email', 'admin@example.com')->first();
        $this->device = $this->admin->devices()->create(['label' => 'Portátil de test']);
    }

    public function test_la_subida_de_media_calcula_hash_sha256(): void
    {
        Storage::fake('public');

        $media = app(MediaService::class)->store(
            UploadedFile::fake()->image('foto.jpg', 800, 600),
            $this->admin->id,
        );

        $this->assertNotNull($media->hash);
        $this->assertSame(64, strlen((string) $media->hash));
        $this->assertNotNull($media->thumbnail_path);
    }

    public function test_la_metadata_de_media_del_device_se_envia_al_central(): void
    {
        Storage::fake('public');
        config(['tcms.runtime' => 'native-desktop']);

        $media = app(MediaService::class)->store(
            UploadedFile::fake()->image('offline.jpg', 800, 600),
            $this->admin->id,
        );

        // Simulación de dos bases: el central no ha visto la fila (más antigua).
        $media->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

        $summary = app(PushService::class)->sync($this->device->fresh());

        // La fila viaja por el protocolo normal (media es bidi).
        $this->assertSame(1, $summary['applied']);
        $this->assertDatabaseHas('media', ['uuid' => $media->uuid, 'hash' => $media->hash]);
    }

    public function test_el_central_detecta_ficheros_faltantes_y_los_recibe(): void
    {
        Storage::fake('public');

        $media = app(MediaService::class)->store(
            UploadedFile::fake()->image('central.jpg', 800, 600),
            $this->admin->id,
        );

        $endpoint = app(MediaSyncEndpoint::class);

        // Con el fichero en su sitio, el central no pide nada.
        $this->assertSame(
            [],
            $endpoint->missing([['uuid' => $media->uuid, 'hash' => $media->hash]])['missing'],
        );

        // Sin fichero (o con hash distinto), sí: ahí debe subirlo el device.
        Storage::disk('public')->delete($media->path);
        $this->assertSame(
            [$media->uuid],
            $endpoint->missing([['uuid' => $media->uuid, 'hash' => $media->hash]])['missing'],
        );

        // Recepción: los bytes del device aterrizan y se regenera la miniatura.
        $received = $endpoint->receive($media->uuid, UploadedFile::fake()->image('offline.jpg', 800, 600));

        Storage::disk('public')->assertExists($media->path);
        $this->assertSame($received->hash, hash_file('sha256', Storage::disk('public')->path($media->path)));
        $this->assertNotNull($received->thumbnail_path);
    }

    public function test_pushfiles_y_pullfiles_del_device_con_gateway_falso(): void
    {
        Storage::fake('public');

        $media = app(MediaService::class)->store(
            UploadedFile::fake()->image('local.jpg', 800, 600),
            $this->admin->id,
        );

        $bytes = Storage::disk('public')->get($media->path);

        // Gateway falso: el central "no tiene" este fichero, y al descargar
        // devuelve los bytes que conocemos (simula las dos bases reales).
        $uploads = [];

        $gateway = new class($bytes, $uploads) implements SyncGateway
        {
            /** @var array<int, array{uuid: string, path: string}> */
            private array $uploads;

            public function __construct(
                private readonly string $bytes,
                array &$uploads,
            ) {
                $this->uploads = &$uploads;
            }

            /** @return array<int, array{uuid: string, path: string}> */
            public function uploaded(): array
            {
                return $this->uploads;
            }

            public function pull(Device $device): array
            {
                return ['changes' => [], 'pivots' => [], 'cursor' => 0, 'has_more' => false];
            }

            public function push(Device $device, array $changes): array
            {
                return ['results' => []];
            }

            public function checkMedia(Device $device, array $files): array
            {
                return ['missing' => array_column($files, 'uuid')];
            }

            public function uploadMedia(Device $device, string $uuid, string $absolutePath): bool
            {
                $this->uploads[] = ['uuid' => $uuid, 'path' => $absolutePath];

                return true;
            }

            public function downloadMedia(Device $device, string $uuid): ?string
            {
                return $this->bytes;
            }
        };

        $service = new MediaFileSyncService($gateway);

        // Push: el fichero local se ofrece y el central (falso) lo pide.
        $this->assertSame(1, $service->pushFiles($this->device->fresh()));
        $this->assertSame($media->uuid, $gateway->uploaded()[0]['uuid'] ?? null);
        $this->assertFileExists($gateway->uploaded()[0]['path']);

        // Pull: el device escribe los bytes y anota el hash del central.
        Storage::disk('public')->delete($media->path);
        $this->assertSame(1, $service->pullFiles($this->device->fresh()));
        Storage::disk('public')->assertExists($media->path);
        $this->assertSame(hash('sha256', $bytes), $media->fresh()->hash);

        // Con el fichero ya correcto, no se vuelve a descargar.
        $this->assertSame(0, $service->pullFiles($this->device->fresh()));
    }
}
