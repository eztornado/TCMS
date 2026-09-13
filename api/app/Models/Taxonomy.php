<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Taxonomía global (categorías jerárquicas, etiquetas planas) aplicable a
 * cualquier modelo marcado como taxonomizable. Si `custom_model_id` es
 * null aplica a todos; si no, queda limitada a ese modelo.
 */
class Taxonomy extends Model
{
    protected $fillable = ['custom_model_id', 'name', 'slug', 'plural_label', 'is_hierarchical'];

    protected function casts(): array
    {
        return ['is_hierarchical' => 'boolean'];
    }

    public function customModel(): BelongsTo
    {
        return $this->belongsTo(CustomModel::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('sort');
    }

    /** Taxonomías aplicables a un modelo: globales + propias. */
    public static function applicableTo(CustomModel $model): Collection
    {
        return Cache::remember("cm.{$model->slug}.taxonomies", 600, fn () => static::query()
            ->where(fn ($q) => $q->whereNull('custom_model_id')->orWhere('custom_model_id', $model->id))
            ->with('terms')
            ->get());
    }

    protected static function booted(): void
    {
        static::saved(fn (self $t) => Cache::flush());
        static::deleted(fn (self $t) => Cache::flush());
    }
}
