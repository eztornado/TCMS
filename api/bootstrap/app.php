<?php

use App\Exceptions\AppException;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ResolveCustomModel;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Autenticación SPA de Sanctum: las peticiones desde el front (mismo
        // dominio en producción) comparten la sesión por cookies.
        $middleware->statefulApi();

        $middleware->alias([
            'permission' => EnsureUserHasPermission::class,
            'role' => EnsureUserHasRole::class,
            'resolve_custom_model' => ResolveCustomModel::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Errores de negocio con códigos estables y su HTTP status real.
        $exceptions->render(function (AppException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->businessCode,
                'errors' => $e->errors,
            ], $e->status);
        });

        // 401 sin la verbosidad por defecto de Laravel en rutas API.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }
        });
    })->create();
