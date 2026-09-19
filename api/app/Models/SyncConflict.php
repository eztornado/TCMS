<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Conflicto de edición concurrente resuelto por LWW a favor del central.
 * El payload central queda registrado: es lo que el device aplica en su
 * siguiente pull para converger.
 */
class SyncConflict extends Model
{
    protected $table = 'sync_conflicts';

    protected $fillable = [
        'table_name', 'uuid', 'origin_device_uuid',
        'local_updated_at', 'central_updated_at', 'central_payload',
    ];

    protected function casts(): array
    {
        return [
            'local_updated_at' => 'datetime',
            'central_updated_at' => 'datetime',
            'central_payload' => 'array',
        ];
    }
}
