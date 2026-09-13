<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(
            Role::query()->with('permissions')->withCount('users')->orderBy('id')->get(),
        );
    }

    public function store(Request $request): RoleResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'label' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists(Permission::class, 'name')],
        ]);

        $role = Role::create(['name' => $data['name'], 'label' => $data['label'] ?? null]);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return new RoleResource($role->load('permissions'));
    }

    public function update(Request $request, Role $role): RoleResource
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists(Permission::class, 'name')],
        ]);

        // El `name` es identificador técnico estable: no se renombra.
        $role->update(['label' => $data['label'] ?? null, 'description' => $data['description'] ?? null]);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return new RoleResource($role->load('permissions'));
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->count() > 0) {
            abort(422, 'El rol tiene usuarios asignados.');
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Rol eliminado.']);
    }

    /** Catálogo de permisos agrupado por recurso (para el editor de roles). */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()->orderBy('name')->get(['id', 'name']);

        $grouped = $permissions->groupBy(fn ($p) => $this->groupOf($p->name))
            ->map(fn ($items, $group) => [
                'group' => $group,
                'permissions' => $items->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            ])
            ->values();

        return response()->json(['data' => $grouped]);
    }

    protected function groupOf(string $permission): string
    {
        // list-products → products | list-cm-paginas → cm:paginas | manage-settings → settings
        if (str_starts_with($permission, 'list-cm-')) {
            return 'cm:'.str_replace('list-cm-', '', $permission);
        }
        if (preg_match('/^[a-z]+-(.+)$/', $permission, $m)) {
            return $m[1];
        }

        return $permission;
    }
}
