<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marca de borrado para replays: un device nuevo (o resincronizado) consulta
 * aquí qué filas ya no existen en el central.
 */
class SyncTombstone extends Model
{
    public $timestamps = false;

    protected $table = 'sync_tombstones';

    protected $fillable = ['table_name', 'uuid', 'origin_device_uuid', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }
}
