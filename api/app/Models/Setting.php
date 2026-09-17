<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'is_public', 'sort'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_public' => 'boolean',
        ];
    }

    /** Valor cacheado por clave (invalidado al guardar). Cachea array plano:
     * los objetos serializados en caché fichero rompen entre procesos. */
    public static function value(string $key, mixed $default = null): mixed
    {
        $settings = Cache::remember('tcms.settings', 3600, fn () => static::query()->pluck('value', 'key')->all());

        return $settings[$key] ?? $default;
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('tcms.settings'));
        static::deleted(fn () => Cache::forget('tcms.settings'));
    }
}
