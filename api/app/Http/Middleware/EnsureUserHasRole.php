<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Middleware de rol para rutas: `->middleware('role:Admin')`. */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! collect($roles)->some(fn ($r) => $user->hasRole($r))) {
            abort(403, 'Rol insuficiente para esta acción.');
        }

        return $next($request);
    }
}
