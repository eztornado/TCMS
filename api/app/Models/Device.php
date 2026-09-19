<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Instalación nativa (desktop/móvil) registrada para sincronizar con el
 * central. Revocar el device invalida el token cuyo name es su uuid.
 */
class Device extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'label', 'platform', 'app_version',
        'last_pull_cursor', 'last_synced_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_pull_cursor' => 'integer',
            'last_synced_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Devices que aún pueden sincronizar. */
    public function scopeActive($query): void
    {
        $query->whereNull('revoked_at');
    }

    protected static function booted(): void
    {
        static::creating(function (self $device) {
            $device->uuid ??= (string) Str::uuid();
        });
    }
}
