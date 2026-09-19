<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fichero de la biblioteca de medios. Guardamos PATH relativo al disco
 * (no URL absoluta: así un cambio de dominio/CDN no rompe el contenido,
 * que era el fallo del sistema de rotary).
 */
class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'uuid', 'user_id', 'name', 'file_name', 'disk', 'path', 'hash',
        'thumbnail_path', 'mime_type', 'size', 'width', 'height', 'alt', 'title',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** URL pública del fichero original. */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /** URL de la miniatura (o la del original si no tiene). */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_path) {
            return $this->isImage() ? $this->url : null;
        }

        return Storage::disk($this->disk)->url($this->thumbnail_path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    protected static function booted(): void
    {
        static::creating(function (self $media) {
            $media->uuid ??= (string) Str::uuid();
        });

        // Elimina los ficheros físicos al borrar la fila.
        static::deleting(function (self $media) {
            Storage::disk($media->disk)->delete(array_filter([$media->path, $media->thumbnail_path]));
        });
    }
}
