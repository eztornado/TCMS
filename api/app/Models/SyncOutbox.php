<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cambio local del device pendiente de push. Se escribe en la misma
 * transacción que el cambio de dominio y se limpia con los acks del central.
 */
class SyncOutbox extends Model
{
    protected $table = 'sync_outbox';

    protected $fillable = ['table_name', 'uuid', 'op', 'payload', 'attempts', 'last_error'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
        ];
    }
}
