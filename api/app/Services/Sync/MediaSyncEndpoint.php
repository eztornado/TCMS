<?php

namespace App\Services\Sync;

use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lado CENTRAL del canal de ficheros de media. La metadata viaja por el
 * protocolo normal (tabla media, bidi); los bytes por aquí:
 *  - missing(): al device le decimos qué ficheros nos faltan (o difieren).
 *  - receive(): el device nos sube su fichero (subida offline).
 *  - stream(): el device descarga el fichero que le falta.
 */
final class MediaSyncEndpoint
{
    public function __construct(private readonly MediaService $media) {}

    /**
     * @param  array<int, array{uuid: string, hash?: ?string}>  $files
     * @return array{missing: array<int, string>}
     */
    public function missing(array $files): array
    {
        $missing = [];

        foreach ($files as $file) {
            $media = Media::query()->where('uuid', (string) $file['uuid'])->first();

            if ($media === null
                || ! Storage::disk($media->disk)->exists($media->path)
                || ($media->hash !== null && $media->hash !== ($file['hash'] ?? null))) {
                $missing[] = (string) $file['uuid'];
            }
        }

        return ['missing' => $missing];
    }

    /** Guarda los bytes del device sobre la fila de media ya sincronizada. */
    public function receive(string $uuid, UploadedFile $file): Media
    {
        $media = Media::query()->where('uuid', $uuid)->firstOrFail();

        $directory = str_contains($media->path, '/')
            ? substr($media->path, 0, (int) strrpos($media->path, '/'))
            : '';

        Storage::disk($media->disk)->putFileAs($directory, $file, basename($media->path));

        $media->forceFill(['hash' => hash_file('sha256', $file->getRealPath())])->save();

        // El central regenera su miniatura (GD disponible en el servidor).
        $this->media->ensureThumbnail($media->fresh());

        return $media->fresh();
    }

    /** Bytes del fichero original para el device. */
    public function stream(string $uuid): StreamedResponse
    {
        $media = Media::query()->where('uuid', $uuid)->firstOrFail();

        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->download($media->path, $media->file_name);
    }
}
