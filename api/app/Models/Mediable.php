<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Pivot polimórfica: cualquier entidad puede adjuntar medios por colección. */
class Mediable extends Model
{
    public $timestamps = false;

    protected $fillable = ['media_id', 'mediable_type', 'mediable_id', 'collection_name', 'sort', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
