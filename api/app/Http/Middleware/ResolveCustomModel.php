<?php

namespace App\Http\Middleware;

use App\Models\CustomModel;
use App\Services\Cms\CustomModelRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el CustomModel de la ruta (`/api/cm/{slug}/...`) y lo inyecta en
 * la request, cacheando la definición para evitar consultar metadatos en
 * cada petición (en rotary la definición se releía 5 veces por petición).
 */
class ResolveCustomModel
{
    public function __construct(private readonly CustomModelRegistry $registry) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('customModel');

        $customModel = $slug instanceof CustomModel
            ? $slug
            : $this->registry->find($slug);

        if (! $customModel || ! $customModel->is_active) {
            abort(404, 'Modelo de contenido no encontrado.');
        }

        $request->attributes->set('customModel', $customModel);
        $request->route()->setParameter('customModel', $customModel);

        return $next($request);
    }
}
