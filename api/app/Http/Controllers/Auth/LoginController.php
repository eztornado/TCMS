<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Login SPA de Sanctum: valida credenciales y crea la sesión por cookie. */
class LoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'La cuenta está desactivada. Contacta con el administrador.',
            ]);
        }

        Auth::login($user, (bool) $request->boolean('remember'));

        // Solo hay sesión en peticiones stateful (SPA); un cliente de API
        // puro no la trae y el login sigue siendo válido.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        return response()->json([
            'message' => 'Sesión iniciada.',
            'user' => $user->only(['id', 'name', 'email']),
        ]);
    }
}
