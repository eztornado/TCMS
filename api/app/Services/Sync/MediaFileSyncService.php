<?php

namespace App\Services\Sync;

use App\Models\Device;
use App\Models\Media;
use App\Services\Sync\Contracts\SyncGateway;
use Illuminate\Support\Facades\Storage;

/**
 * Lado DEVICE del canal de ficheros: sube al central lo que él creó offline
 * y descarga del central lo que le falta. La decisión de QUÉ transferir se
 * basa en el hash sha256 (nada de comparar bytes).
 */
final class MediaFileSyncService
{
    public function __construct(private readonly SyncGateway $gateway) {}

    /** Sube ficheros locales que el central no tiene. Devuelve cuántos. */
    public function pushFiles(Device $device, int $limit = 50): int
    {
        $candidates = Media::query()
            ->whereNotNull('hash')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(fn (Media $media) => Storage::disk($media->disk)->exists($media->path))
            ->take($limit);

        if ($candidates->isEmpty()) {
            return 0;
        }

        $missing = $this->gateway->checkMedia(
            $device,
            $candidates->map(fn (Media $m) => ['uuid' => $m->uuid, 'hash' => $m->hash])->values()->all(),
        )['missing'] ?? [];

        if ($missing === []) {
            return 0;
        }

        $uploaded = 0;

        foreach ($candidates->whereIn('uuid', $missing) as $media) {
            $this->gateway->uploadMedia($device, $media->uuid, Storage::disk($media->disk)->path($media->path));
            $uploaded++;
        }

        return $uploaded;
    }

    /** Descarga ficheros del central que faltan (o difieren) localmente. */
    public function pullFiles(Device $device, int $limit = 50): int
    {
        $downloaded = 0;

        $candidates = Media::query()
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->filter(fn (Media $media) => $this->needsFile($media))
            ->take($limit);

        foreach ($candidates as $media) {
            $bytes = $this->gateway->downloadMedia($device, $media->uuid);

            if ($bytes === null) {
                continue;
            }

            Storage::disk($media->disk)->put($media->path, $bytes);
            $media->forceFill(['hash' => hash('sha256', $bytes)])->saveQuietly();
            $downloaded++;
        }

        return $downloaded;
    }

    private function needsFile(Media $media): bool
    {
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            return true;
        }

        return $media->hash !== null
            && $media->hash !== hash_file('sha256', $disk->path($media->path));
    }
}
