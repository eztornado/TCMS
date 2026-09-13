<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    protected $table = 'terms';

    protected $fillable = ['taxonomy_id', 'parent_id', 'name', 'slug', 'description', 'sort'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    /** Entidades (entradas de custom models) etiquetadas con este término. */
    public function termables(): BelongsToMany
    {
        return $this->belongsToMany(
            DynamicModel::class,
            'termgables',
            'term_id',
            'termgable_id',
        );
    }
}
