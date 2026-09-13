<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('roles')
            ->when($request->q, function ($query, $q) {
                $query->where(fn ($w) => $w
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%"));
            })
            ->when($request->role, fn ($query, $role) => $query->whereHas(
                'roles',
                fn ($r) => $r->where('name', $role),
            ))
            ->when(isset($request->is_active), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->sortableBy(['id', 'name', 'email', 'created_at'], $request)
            ->paginate($request->integer('per_page', 25));

        return UserResource::collection($users);
    }

    public function store(Request $request): UserResource
    {
        $data = $this->validated($request);

        /** @var User $user */
        $user = User::create([...$data, 'password' => Hash::make($data['password'])]);

        if ($data['roles'] ?? []) {
            $user->syncRoles($data['roles']);
        }

        return new UserResource($user->load('roles'));
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles'));
    }

    public function update(Request $request, User $user): UserResource
    {
        $data = $this->validated($request, $user, withPassword: false);

        if (($data['password'] ?? null) !== null) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles'] ?? []);
        }

        return new UserResource($user->load('roles'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            abort(422, 'No puedes eliminar tu propia cuenta.');
        }

        $user->update(['is_active' => false]);
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado.']);
    }

    protected function validated(Request $request, ?User $user = null, bool $withPassword = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user?->id)],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => [Rule::exists(Role::class, 'name')],
        ];

        if ($withPassword) {
            $rules['password'] = ['required', 'string', 'min:8'];
        } else {
            $rules['password'] = ['sometimes', 'nullable', 'string', 'min:8'];
        }

        return $request->validate($rules);
    }
}
