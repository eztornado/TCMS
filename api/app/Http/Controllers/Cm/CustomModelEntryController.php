<?php

namespace App\Http\Controllers\Cm;

use App\Http\Controllers\Controller;
use App\Models\CustomModel;
use App\Models\Media;
use App\Models\Taxonomy;
use App\Services\Cms\CustomModelEntries;
use App\Services\Cms\DynamicModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CRUD genérico de entradas de un custom model. El `schema` endpoint es el
 * contrato con el panel: campos, tipos, visibilidad y validaciones para
 * renderizar listado y formulario sin hardcodear la entidad (patrón
 * schema-driven de rotary/quantumcards, pero con validación real).
 */
class CustomModelEntryController extends Controller
{
    /** Permisos derivados por modelo y acción. */
    private const ACTION_PERMISSION = [
        'schema' => 'list-cm-%s',
        'index' => 'list-cm-%s',
        'store' => 'create-cm-%s',
        'update' => 'edit-cm-%s',
        'destroy' => 'delete-cm-%s',
    ];

    public function schema(Request $request, CustomModel $customModel): JsonResponse
    {
        $this->authorizeAction($request, $customModel, 'schema');

        return response()->json([
            'data' => [
                'slug' => $customModel->slug,
                'label' => $customModel->label,
                'plural_label' => $customModel->plural_label,
                'icon' => $customModel->icon,
                'per_page' => $customModel->per_page,
                'show_field' => $customModel->show_field,
                'has_status' => $customModel->has_status,
                'is_taxonomizable' => $customModel->is_taxonomizable,
                'fields' => $customModel->fields->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'label' => $f->label,
                    'type' => $f->type,
                    'options' => $f->options,
                    'rules' => $f->rules,
                    'default_value' => $f->default_value,
                    'is_required' => $f->is_required,
                    'is_searchable' => $f->is_searchable,
                    'is_filterable' => $f->is_filterable,
                    'is_sortable' => $f->is_sortable,
                    'show_in_list' => $f->show_in_list,
                    'show_in_form' => $f->show_in_form,
                ]),
                'taxonomies' => Taxonomy::applicableTo($customModel)->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'is_hierarchical' => $t->is_hierarchical,
                    'terms' => $t->terms->map(fn ($term) => [
                        'id' => $term->id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'parent_id' => $term->parent_id,
                    ]),
                ]),
            ],
        ]);
    }

    public function index(Request $request, CustomModel $customModel, CustomModelEntries $entries): JsonResource
    {
        $this->authorizeAction($request, $customModel, 'index');

        $page = $entries->list($customModel, $request->query());

        // Bajo `model` (no `meta`) para no pisar la paginación del paginator.
        return JsonResource::collection($page)->additional([
            'model' => ['slug' => $customModel->slug, 'label' => $customModel->label],
        ]);
    }

    public function show(Request $request, CustomModel $customModel, int $id, CustomModelEntries $entries): JsonResponse
    {
        $this->authorizeAction($request, $customModel, 'index');

        $entry = $entries->findOrFail($customModel, $id);

        return response()->json(['data' => $this->serializeEntry($customModel, $entry)]);
    }

    public function store(Request $request, CustomModel $customModel, CustomModelEntries $entries): JsonResponse
    {
        $this->authorizeAction($request, $customModel, 'store');

        $entry = $entries->create($customModel, $request->all());

        return response()->json(['data' => $this->serializeEntry($customModel, $entry), 'message' => 'Entrada creada.'], 201);
    }

    public function update(Request $request, CustomModel $customModel, int $id, CustomModelEntries $entries): JsonResponse
    {
        $this->authorizeAction($request, $customModel, 'update');

        $entry = $entries->findOrFail($customModel, $id);
        $entries->update($customModel, $entry, $request->all());

        return response()->json(['data' => $this->serializeEntry($customModel, $entry->refresh()), 'message' => 'Entrada actualizada.']);
    }

    public function destroy(Request $request, CustomModel $customModel, int $id, CustomModelEntries $entries): JsonResponse
    {
        $this->authorizeAction($request, $customModel, 'destroy');

        $entry = $entries->findOrFail($customModel, $id);
        $entries->delete($customModel, $entry);

        return response()->json(['message' => 'Entrada eliminada.']);
    }

    /** Serializa la entrada con sus términos y medios adjuntos. */
    protected function serializeEntry(CustomModel $model, DynamicModel $entry): array
    {
        $data = $entry->getAttributes();

        foreach ($entry->fields() as $field) {
            if ($field->typeEnum()->value === 'media' && $data[$field->name] ?? null) {
                $media = Media::find($data[$field->name]);
                $data[$field->name.'_url'] = $media?->thumbnail_url ?? $media?->url;
            }
        }

        return [
            'id' => $entry->getKey(),
            ...$data,
            'status' => $model->has_status ? $entry->getAttribute('status') : null,
            'terms' => $model->is_taxonomizable ? $entry->terms()->get(['terms.id', 'terms.name', 'terms.slug']) : null,
            'created_at' => $entry->getAttribute('created_at'),
            'updated_at' => $entry->getAttribute('updated_at'),
        ];
    }

    protected function authorizeAction(Request $request, CustomModel $model, string $action): void
    {
        $permission = sprintf(self::ACTION_PERMISSION[$action], $model->slug);

        if (! $request->user()->can($permission)) {
            abort(403, "Sin permiso: {$permission}");
        }
    }
}
