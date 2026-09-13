<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Registro de un Custom Model. Es un único modelo Eloquent que se configura
 * en runtime (tabla, casts, taxonomías) a partir de la definición almacenada:
 * mismo contrato que un modelo escrito, sin generar clases PHP.
 */
class DynamicEntry extends BaseModel
{
    public CustomModel $definition;

    protected $guarded = []; // la validación se hace con EntryValidator, no por asignación masiva

    public static function for(CustomModel $definition): self
    {
        $entry = new static;
        $entry->definition = $definition;
        $entry->setTable($definition->table_name);

        return $entry;
    }

    public function getDefinition(): CustomModel
    {
        return $this->definition;
    }

    /** Casts dinámicos según el tipo de cada campo. */
    protected function casts(): array
    {
        $casts = [];

        foreach ($this->definition?->fields ?? [] as $field) {
            $casts[$field->name] = match ($field->type) {
                CustomFieldType::Number => 'integer',
                CustomFieldType::Decimal => 'decimal:2',
                CustomFieldType::Boolean => 'boolean',
                CustomFieldType::Date => 'date:Y-m-d',
                CustomFieldType::DateTime => 'datetime',
                CustomFieldType::MultiSelect, CustomFieldType::Repeater, CustomFieldType::Json => 'json',
                default => 'string',
            };
        }

        return $casts;
    }

    /** Términos de taxonomía (sólo en modelos taxonomizables). */
    public function terms(): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termgable');
    }

    /** Adjuntos de la biblioteca de medios por colección (campo de tipo media). */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->withPivot('collection_name', 'sort');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $searchable = $this->definition->fields
            ->filter(fn (CustomModelField $f) => $f->is_searchable)
            ->map(fn (CustomModelField $f) => $f->name);

        return $query->where(function (Builder $q) use ($searchable, $term) {
            foreach ($searchable as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    public function toApiArray(): array
    {
        $data = $this->attributesToArray();

        if ($this->definition?->is_taxonomizable) {
            $data['terms'] = $this->relationLoaded('terms')
                ? $this->terms->map(fn (Term $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'taxonomy_slug' => $t->taxonomy?->slug,
                ])->all()
                : [];
        }

        return $data;
    }
}
