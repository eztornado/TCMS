<?php

use App\Http\Controllers\Ui\SpaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SpaController::class, 'index']);

// BrowserRouter del panel embebido: cualquier ruta no resuelta por el API
// devuelve index.html cuando hay SPA en public/ui (apps nativas). Sin ella
// (web Docker) cae en 404, igual que hoy.
Route::fallback([SpaController::class, 'fallback']);
