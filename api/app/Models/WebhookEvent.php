<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Idempotencia de webhooks de pasarela: un external_id solo se procesa una vez. */
class WebhookEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['gateway', 'external_id', 'type', 'payload', 'processed_at', 'error', 'created_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'json',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
