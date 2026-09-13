<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Pivot polimórfica entrada ↔ término de taxonomía. */
class Termable extends Model
{
    public $timestamps = false;

    protected $table = 'termgables';

    protected $fillable = ['term_id', 'termgable_type', 'termgable_id'];

    public function termable(): MorphTo
    {
        return $this->morphTo();
    }
}
