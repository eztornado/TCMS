<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Identidad de sincronización: uuid estable que viaja entre el central y los
 * devices. Los ids enteros son locales de cada base y nunca se sincronizan.
 */
trait Syncable
{
    public static function bootSyncable(): void
    {
        static::creating(function ($model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }
}
