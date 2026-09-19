<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro append-only de cambios del central (infraestructura de sync, no
 * se sincroniza a sí misma). El id es el cursor de pull de cada device.
 */
class SyncLog extends Model
{
    protected $table = 'sync_log';

    protected $fillable = ['table_name', 'uuid', 'op', 'payload', 'origin_device_uuid'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
