<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Cms\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Perfil del usuario autenticado y su menú filtrado por permisos. */
class MeController extends Controller
{
    public function __construct(private readonly MenuService $menus) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['roles', 'permissions']));
    }

    public function menu(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->menus->treeFor($this->menus->slugForAdmin(), $request->user()),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => 'Contraseña actualizada.']);
    }
}
