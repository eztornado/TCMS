<?php

namespace App\Services\Cms;

use App\Enums\CustomFieldType;
use App\Exceptions\AppException;
use App\Models\CustomModel;
use App\Models\CustomModelField;
use App\Models\Media;
use App\Models\Term;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * CRUD genérico sobre entradas de un custom model: validación declarativa
 * por campo, búsqueda/filtro/ordenación con whitelist contra la definición
 * (en rotary cualquier query string era un filtro y cualquier parámetro un
 * orderBy: inyección de columnas) y adjunción de taxonomías y medios.
 */
class CustomModelEntries
{
    public function __construct(private readonly CustomModelSchema $schema) {}

    public function list(CustomModel $model, array $query): LengthAwarePaginator
    {
        $dynamic = DynamicModel::for($model);
        $builder = $dynamic->newQuery();

        if ($search = $query['q'] ?? null) {
            $searchables = $model->fields->where('is_searchable')->pluck('name');
            $builder->where(function ($q) use ($searchables, $search) {
                foreach ($searchables as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        // Filtros exactos solo sobre campos marcados como filtrables.
        foreach ($model->fields->where('is_filterable') as $field) {
            $value = $query['filter'][$field->name] ?? null;
            if ($value !== null && $value !== '') {
                $builder->where($field->name, $value);
            }
        }

        if ($model->has_status && ($status = $query['status'] ?? null)) {
            $builder->where('status', $status);
        }

        $termId = $query['term_id'] ?? null;
        if ($termId) {
            $builder->whereHas('terms', fn ($q) => $q->where('terms.id', (int) $termId));
        } elseif ($taxonomySlug = $query['taxonomy'] ?? null) {
            $builder->whereHas('terms.taxonomy', fn ($q) => $q->where('taxonomies.slug', $taxonomySlug));
        }

        $sortField = $query['sort'] ?? $model->sort_field ?? 'id';
        if ($model->fields->firstWhere('name', $sortField)?->is_sortable || in_array($sortField, ['id', 'created_at', 'updated_at'], true)) {
            $builder->orderBy($sortField, $query['dir'] ?? 'desc');
        }

        return $builder->paginate(min((int) ($query['per_page'] ?? $model->per_page), 100));
    }

    public function findOrFail(CustomModel $model, int $id): DynamicModel
    {
        $entry = DynamicModel::for($model)->newQuery()->find($id);

        if (! $entry) {
            throw AppException::notFound('Entrada');
        }

        return $entry;
    }

    /**
     * @throws ValidationException
     */
    public function create(CustomModel $model, array $data): DynamicModel
    {
        $validated = $this->validate($model, $data);

        return DB::transaction(function () use ($model, $validated, $data) {
            $entry = DynamicModel::for($model, $validated);
            $entry->save();

            $this->syncRelations($entry, $model, $validated, $data);

            return $entry;
        });
    }

    public function update(CustomModel $model, DynamicModel $entry, array $data): DynamicModel
    {
        $validated = $this->validate($model, $data, $entry->getKey());

        DB::transaction(function () use ($entry, $model, $validated, $data) {
            $entry->fill($validated)->save();
            $this->syncRelations($entry, $model, $validated, $data);
        });

        return $entry->refresh();
    }

    public function delete(CustomModel $model, DynamicModel $entry): void
    {
        $entry->delete();
    }

    /** Adjunta taxonomías, portada y galería. */
    protected function syncRelations(DynamicModel $entry, CustomModel $model, array $validated, array $data): void
    {
        if ($model->is_taxonomizable && isset($data['terms'])) {
            $termIds = Term::query()->whereIn('id', (array) $data['terms'])->pluck('id');
            $entry->terms()->sync($termIds);
        }

        foreach (['cover' => 'cover_media_id', 'gallery' => 'gallery_media_ids'] as $collection => $input) {
            if (! array_key_exists($input, $data)) {
                continue;
            }
            $ids = $collection === 'cover'
                ? array_filter([(int) $data[$input]])
                : array_map(intval(...), (array) $data[$input]);

            $existing = Media::whereIn('id', $ids)->pluck('id');
            $entry->attachedMedia($collection)->sync(
                $existing->mapWithKeys(fn ($id, $i) => [$id => ['collection_name' => $collection, 'sort' => $i]])->all(),
            );
        }
    }

    /** Reglas de validación derivadas de los campos declarados. */
    protected function validate(CustomModel $model, array $data, ?int $ignoreId = null): array
    {
        $rules = [];
        $fields = $model->fields->where('show_in_form');

        foreach ($fields as $field) {
            $fieldRules = $field->validationRules();
            if ($field->is_required) {
                $fieldRules[] = 'required';
            }
            $fieldRules[] = $this->typeRule($field);
            $rules[$field->name] = $fieldRules ?: ['nullable'];
        }

        $extra = [];
        if ($model->is_taxonomizable) {
            $extra['terms'] = ['sometimes', 'array'];
            $extra['terms.*'] = ['integer'];
        }
        $extra['cover_media_id'] = ['sometimes', 'nullable', 'integer'];
        $extra['gallery_media_ids'] = ['sometimes', 'array'];
        $extra['status'] = ['sometimes', 'in:draft,published,archived'];

        $validated = Validator::validate($data, [...$rules, ...$extra]);

        return collect($validated)
            ->only([...$fields->pluck('name'), 'status'])
            ->all();
    }

    protected function typeRule(CustomModelField $field): string
    {
        return match ($field->typeEnum()) {
            CustomFieldType::Number => 'integer',
            CustomFieldType::Decimal => 'numeric',
            CustomFieldType::Boolean => 'boolean',
            CustomFieldType::Date, CustomFieldType::DateTime => 'date',
            CustomFieldType::Media, CustomFieldType::Relation => 'integer',
            CustomFieldType::Json, CustomFieldType::MultiSelect => 'array',
            CustomFieldType::Select => 'string',
            default => 'string',
        };
    }
}
