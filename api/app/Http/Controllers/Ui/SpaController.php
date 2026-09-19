<?php

namespace App\Http\Controllers\Ui;

use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve el panel React embebido cuando existe el build en public/ui
 * (apps NativePHP: el webview carga el propio Laravel). En web el contenedor
 * Docker no tiene public/ui y el panel lo sigue sirviendo nginx, igual que hoy.
 */
final class SpaController
{
    /** Prefijos que nunca captura el fallback de la SPA (API e infraestructura). */
    private const RESERVED_PREFIXES = '^(api|sanctum|storage|up)(/|$)';

    /** GET / — raíz del panel embebido (o view('welcome') en web). */
    public function index(): Response|View
    {
        return $this->serve('') ?? view('welcome');
    }

    /**
     * Fallback de rutas del panel (BrowserRouter: /users, /settings/...).
     * Solo actúa con UI embebida; sin ella mantiene el 404 actual.
     */
    public function fallback(): Response
    {
        if (preg_match('#'.self::RESERVED_PREFIXES.'#', (string) request()->path())) {
            abort(404);
        }

        return $this->serve((string) request()->path()) ?? abort(404);
    }

    /** Sirve index.html o un asset físico del build; null si no hay UI embebida. */
    private function serve(string $path): ?Response
    {
        $base = public_path('ui');
        $index = $base.'/index.html';

        if (! is_file($index)) {
            return null;
        }

        // Asset físico del build (p. ej. assets/app-1a2b3c.js). Se limpia la
        // ruta para impedir traversal y se sirve con caché inmutable si lleva
        // hash en el nombre.
        $asset = str_replace(['..', '\\', "\0"], '', trim($path, '/'));
        if ($asset !== '' && is_file($base.'/'.$asset)) {
            $response = response()->file($base.'/'.$asset);
            if (str_starts_with($asset, 'assets/')) {
                $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
            }

            return $response;
        }

        // index.html nunca se cachea: es el punto de entrada del router.
        return response()->file($index, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
