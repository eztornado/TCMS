<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Middleware de permiso para rutas: `->middleware('permission:list-users')`. */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! collect($permissions)->some(fn ($p) => $user->can($p))) {
            abort(403, 'Sin permisos para esta acción.');
        }

        return $next($request);
    }
}
