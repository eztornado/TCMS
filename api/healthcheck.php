<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

// Healthcheck ligero para Docker/Coolify: sin conexión a BD ni sesión.
// Responde rápido y solo falla si el framework no arranca.

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::create('/up', 'GET'),
);

http_response_code($response->getStatusCode());
header('Content-Type: application/json');

exit($response->getStatusCode() === 200 ? 0 : 1);
