<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Subida de ficheros con miniatura automática para imágenes (GD nativo).
 * Centraliza lo que en rotary estaba copiado en 3 controladores con
 * procesado base64 inline duplicado.
 */
class MediaService
{
    public const MAX_SIZE_KB = 10240;

    public const ALLOWED_MIMES = 'jpg,jpeg,png,webp,gif,svg,pdf,mp4,zip';

    public const THUMB_WIDTH = 480;

    public function store(UploadedFile $file, ?int $userId = null): Media
    {
        $disk = 'public';
        $directory = 'media/'.now()->format('Y/m');
        $path = $file->store($directory, $disk);

        [$width, $height] = $this->dimensions($file);

        $media = Media::create([
            'user_id' => $userId ?? auth()->id(),
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'width' => $width,
            'height' => $height,
        ]);

        if ($media->isImage() && $media->mime_type !== 'image/svg+xml') {
            try {
                $media->update(['thumbnail_path' => $this->thumbnail($media)]);
            } catch (\Throwable $e) {
                logger()->warning('No se pudo generar la miniatura', ['media' => $media->id, 'e' => $e->getMessage()]);
            }
        }

        return $media;
    }

    /** Miniatura WebP de 480 px de ancho máximo, preservando transparencia. */
    protected function thumbnail(Media $media): string
    {
        $source = imagecreatefromstring(Storage::disk($media->disk)->get($media->path));

        if ($source === false) {
            throw new \RuntimeException('Imagen ilegible.');
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $dstW = min(self::THUMB_WIDTH, $srcW);
        $dstH = (int) round($srcH * ($dstW / $srcW));

        $thumb = imagecreatetruecolor($dstW, $dstH);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        $path = Str::replaceLast('.', '_thumb.webp', $media->path);
        imagewebp($thumb, Storage::disk($media->disk)->path($path), 80);

        imagedestroy($source);
        imagedestroy($thumb);

        return $path;
    }

    /** @return array{0:?int,1:?int} */
    protected function dimensions(UploadedFile $file): array
    {
        if (! str_starts_with((string) $file->getMimeType(), 'image/') || $file->getMimeType() === 'image/svg+xml') {
            return [null, null];
        }

        $info = @getimagesize($file->getRealPath());

        return $info ? [$info[0], $info[1]] : [null, null];
    }
}
