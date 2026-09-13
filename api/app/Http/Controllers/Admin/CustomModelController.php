<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Models\CustomModel;
use App\Models\CustomModelField;
use App\Services\Cms\CustomModelRegistry;
use App\Services\Cms\CustomModelSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Builder de contenido dinámico: crea la definición + la tabla física,
 * sincroniza campos como columnas y registra los permisos derivados
 * {list,create,edit,delete}-cm-{slug} (en rotary el builder ni existía en
 * el repo: había que escribir migraciones-semilla a mano).
 */
class CustomModelController extends Controller
{
    public function __construct(
        private readonly CustomModelSchema $schema,
        private readonly CustomModelRegistry $registry,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CustomModel::query()->withCount('fields')->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $model = DB::transaction(function () use ($data) {
            /** @var CustomModel $model */
            $model = CustomModel::create([...$data, 'created_by' => auth()->id()]);
            $this->createFields($model, $data['fields'] ?? []);
            $this->schema->create($model->refresh());
            $this->registerPermissions($model->slug);

            return $model;
        });

        return response()->json(['data' => $model->load('fields'), 'message' => 'Modelo creado.'], 201);
    }

    public function update(Request $request, CustomModel $customModel): JsonResponse
    {
        $data = $this->validated($request, $customModel, withFields: false);

        $customModel->update($data);

        return response()->json(['data' => $customModel->load('fields'), 'message' => 'Modelo actualizado.']);
    }

    public function destroy(CustomModel $customModel): JsonResponse
    {
        DB::transaction(function () use ($customModel) {
            $this->schema->drop($customModel);
            $customModel->delete();
        });

        return response()->json(['message' => 'Modelo eliminado.']);
    }

    /** Nombres que el motor reserva (columnas técnicas de toda tabla generada). */
    private const RESERVED_FIELD_NAMES = ['id', 'status', 'published_at', 'created_by', 'created_at', 'updated_at', 'deleted_at'];

    /** Alta/baja/sincronización de un campo = ALTER TABLE sobre la tabla física. */
    public function storeField(Request $request, CustomModel $customModel): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/',
                'not_in:'.implode(',', self::RESERVED_FIELD_NAMES),
                Rule::unique('custom_model_fields', 'name')->where('custom_model_id', $customModel->id)],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'options' => ['nullable', 'array'],
            'rules' => ['nullable', 'array'],
            'default_value' => ['nullable', 'string'],
            'is_required' => ['boolean'],
            'is_unique' => ['boolean'],
            'is_searchable' => ['boolean'],
            'is_filterable' => ['boolean'],
            'is_sortable' => ['boolean'],
            'show_in_list' => ['boolean'],
            'show_in_form' => ['boolean'],
            'sort' => ['integer'],
        ]);

        /** @var CustomModelField $field */
        $field = $customModel->fields()->create($data);
        $this->schema->syncField($field->refresh());

        return response()->json(['data' => $field, 'message' => 'Campo añadido.'], 201);
    }

    public function updateField(Request $request, CustomModelField $field): JsonResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'options' => ['nullable', 'array'],
            'rules' => ['nullable', 'array'],
            'default_value' => ['nullable', 'string'],
            'is_required' => ['boolean'],
            'is_unique' => ['boolean'],
            'is_searchable' => ['boolean'],
            'is_filterable' => ['boolean'],
            'is_sortable' => ['boolean'],
            'show_in_list' => ['boolean'],
            'show_in_form' => ['boolean'],
            'sort' => ['integer'],
        ]);

        // name y type no cambian tras crearse (evita ALTER destructivo).
        $field->update($data);

        return response()->json(['data' => $field, 'message' => 'Campo actualizado.']);
    }

    public function destroyField(CustomModelField $field): JsonResponse
    {
        $this->schema->removeField($field);
        $field->delete();

        return response()->json(['message' => 'Campo eliminado.']);
    }

    protected function validated(Request $request, ?CustomModel $existing = null, bool $withFields = true): array
    {
        $rules = [
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('custom_models', 'slug')->ignore($existing?->id)],
            'label' => ['required', 'string', 'max:255'],
            'plural_label' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'per_page' => ['integer', 'min:5', 'max:100'],
            'show_field' => ['nullable', 'string', 'max:64'],
            'has_status' => ['boolean'],
            'is_taxonomizable' => ['boolean'],
            'is_active' => ['boolean'],
        ];

        if ($withFields) {
            $rules['fields'] = ['array'];
            $rules['fields.*.name'] = ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/',
                'not_in:'.implode(',', self::RESERVED_FIELD_NAMES)];
            $rules['fields.*.label'] = ['required', 'string', 'max:255'];
            $rules['fields.*.type'] = ['required', Rule::enum(CustomFieldType::class)];
            // Sin reglas explícitas, validated() descartaría estos flags.
            $rules['fields.*.options'] = ['nullable', 'array'];
            $rules['fields.*.rules'] = ['nullable', 'array'];
            $rules['fields.*.default_value'] = ['nullable', 'string'];
            foreach (['is_required', 'is_unique', 'is_searchable', 'is_filterable', 'is_sortable', 'show_in_list', 'show_in_form'] as $flag) {
                $rules["fields.*.$flag"] = ['boolean'];
            }
        }

        $data = $request->validate($rules);

        if ($withFields && ! isset($data['table_name'])) {
            $data['table_name'] = 'cm_'.$data['slug'];
        }
        $data['plural_label'] ??= $data['label'].'s';

        return $data;
    }

    protected function createFields(CustomModel $model, array $fields): void
    {
        foreach (array_values($fields) as $index => $field) {
            $model->fields()->create([...$field, 'sort' => $index]);
        }
    }

    /** Crea los permisos derivados y los asigna al rol Admin. */
    protected function registerPermissions(string $slug): void
    {
        $adminRole = Role::query()->where('name', 'Admin')->first();

        foreach (['list', 'create', 'edit', 'delete'] as $action) {
            $permission = Permission::firstOrCreate(['name' => "$action-cm-$slug"]);
            $adminRole?->givePermissionTo($permission);
        }
    }
}
