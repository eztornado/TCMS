<?php

namespace App\Services\Sync;

use App\Models\Device;
use App\Models\Media;
use App\Services\Sync\Contracts\SyncGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Transporte en memoria: el mismo proceso hace de central y de device.
 * Lo usa la suite de tests para ejercitar el protocolo completo sin red.
 */
final class LoopbackSyncGateway implements SyncGateway
{
    public function __construct(
        private readonly SyncEndpoint $endpoint,
        private readonly MediaSyncEndpoint $media,
    ) {}

    public function pull(Device $device): array
    {
        return $this->endpoint->pull($device->user, $device);
    }

    public function push(Device $device, array $changes): array
    {
        return $this->endpoint->push($device->user, $device, $changes);
    }

    public function checkMedia(Device $device, array $files): array
    {
        return $this->media->missing($files);
    }

    public function uploadMedia(Device $device, string $uuid, string $absolutePath): bool
    {
        $file = new UploadedFile(
            $absolutePath,
            basename($absolutePath),
            mime_content_type($absolutePath) ?: 'application/octet-stream',
            null,
            true, // test mode: no mueve el fichero original
        );

        $this->media->receive($uuid, $file);

        return true;
    }

    public function downloadMedia(Device $device, string $uuid): ?string
    {
        $media = Media::query()->where('uuid', $uuid)->first();

        if ($media === null || ! Storage::disk($media->disk)->exists($media->path)) {
            return null;
        }

        return Storage::disk($media->disk)->get($media->path);
    }
}
