<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Respuesta idéntica exista o no el email (no se filtran cuentas).
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Si el email existe, recibirás un enlace para restablecer la contraseña.',
        ]);
    }
}
