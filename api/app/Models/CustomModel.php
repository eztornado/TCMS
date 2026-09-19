<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Definición de una entidad de contenido dinámico ("custom post type" de
 * WordPress, pero con esquema activo: los datos viven en una tabla física
 * propia, `table_name`, y `custom_model_fields` describe el esquema).
 */
class CustomModel extends Model
{
    protected $fillable = [
        'slug', 'label', 'plural_label', 'icon', 'table_name', 'per_page',
        'show_field', 'has_status', 'is_taxonomizable', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'per_page' => 'integer',
            'has_status' => 'boolean',
            'is_taxonomizable' => 'boolean',
        ];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CustomModelField::class)->orderBy('sort');
    }

    public function taxonomies(): HasMany
    {
        return $this->hasMany(Taxonomy::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Permisos derivados por modelo: list-cm-productos, etc. */
    public function permissionFor(string $action): string
    {
        return "$action-cm-$this->slug";
    }

    /** Las rutas del panel resuelven el modelo por slug (API amable). */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        // Cuerpos con bloque (nunca flecha): Cache::forget devuelve false
        // cuando la clave no existía y un listener que devuelve false CORTA
        // la cadena de listeners del evento (el observer de sync dejaba de
        // registrar los borrados).
        static::saved(function (self $model): void {
            Cache::forget("cm.$model->slug");
        });
        static::deleted(function (self $model): void {
            Cache::forget("cm.$model->slug");
        });
    }
}
