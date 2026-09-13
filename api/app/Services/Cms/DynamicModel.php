<?php

namespace App\Services\Cms;

use App\Enums\CustomFieldType;
use App\Models\BaseModel;
use App\Models\CustomModel;
use App\Models\CustomModelField;
use App\Models\Media;
use App\Models\Term;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

/**
 * Modelo Eloquent dinámico: una instancia apunta a la tabla física de un
 * custom model y hereda fillable/casts/hidden de su definición de campos.
 *
 * Uso: DynamicModel::for($customModel)->query()->paginate() — o vía el
 * servicio CustomModelEntries.
 */
class DynamicModel extends BaseModel
{
    protected CustomModel $definition;

    /** @var array<int, CustomModelField> */
    protected array $fieldDefinitions;

    protected $guarded = ['id'];

    public static function for(CustomModel $definition, array $attributes = []): self
    {
        $instance = new static($attributes);
        $instance->bootWithDefinition($definition);

        return $instance;
    }

    protected function bootWithDefinition(CustomModel $definition): void
    {
        $this->definition = $definition;
        $this->fieldDefinitions = $definition->fields->keyBy('name')->all();
        $this->setTable($definition->table_name);
        $this->mergeCasts($this->dynamicCasts());
    }

    /**
     * Los modelos hidratados por el query builder heredan la definición de
     * la instancia que originó la consulta.
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $instance = parent::newInstance($attributes, $exists);

        if (isset($this->definition)) {
            $instance->bootWithDefinition($this->definition);
        }

        return $instance;
    }

    public function definition(): CustomModel
    {
        return $this->definition;
    }

    public function fields(): array
    {
        return $this->fieldDefinitions;
    }

    public function field(string $name): ?CustomModelField
    {
        return $this->fieldDefinitions[$name] ?? null;
    }

    public function hasStatus(): bool
    {
        return $this->definition->has_status;
    }

    /** Casts Eloquent derivados del tipo de campo declarado. */
    protected function dynamicCasts(): array
    {
        $casts = [];
        foreach ($this->fieldDefinitions as $field) {
            $cast = match ($field->typeEnum()) {
                CustomFieldType::Boolean => 'boolean',
                CustomFieldType::Date => 'date:Y-m-d',
                CustomFieldType::DateTime => 'datetime',
                CustomFieldType::Decimal => 'decimal:2',
                CustomFieldType::Number => 'integer',
                CustomFieldType::Json, CustomFieldType::MultiSelect => 'array',
                default => null,
            };
            if ($cast) {
                $casts[$field->name] = $cast;
            }
        }

        return $casts;
    }

    /** Términos de taxonomía adjuntos (todas las taxonomías del modelo). */
    public function terms(?string $taxonomySlug = null): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termgable', 'termgables')
            ->when($taxonomySlug, fn ($q) => $q->whereHas(
                'taxonomy',
                fn ($t) => $t->where('slug', $taxonomySlug),
            ));
    }

    /** Imágenes adjuntas por colección ('cover' | 'gallery'). */
    public function attachedMedia(string $collection = 'cover'): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->withPivot('sort')
            ->wherePivot('collection_name', $collection)
            ->orderByPivot('sort');
    }

    protected static function booted(): void
    {
        // El slug se autogenera si el modelo lo define y llega vacío.
        static::creating(function (self $model) {
            if (! $model->field('slug') || ! empty($model->slug)) {
                return;
            }
            $source = $model->definition->show_field !== 'id'
                ? ($model->getAttribute($model->definition->show_field) ?? '')
                : ($model->getAttribute('title') ?? '');

            $model->slug = Str::slug((string) $source).'-'.Str::lower(Str::random(4));
        });
    }
}
