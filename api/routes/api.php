<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Cm\CustomModelEntryController;
use App\Http\Controllers\Store;
use App\Http\Controllers\Sync;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de TornadoCMS
|--------------------------------------------------------------------------
| Autenticación SPA por cookies (Sanctum). Los guards de permiso usan la
| convención {verbo}-{recurso}.
*/

Route::prefix('auth')->group(function (): void {
    Route::post('login', Auth\LoginController::class)->middleware('throttle:10,1');
    Route::post('forgot-password', Auth\ForgotPasswordController::class)->middleware('throttle:10,1');
    Route::post('reset-password', Auth\ResetPasswordController::class)->middleware('throttle:10,1');
    // Alta de device con credenciales, sin cookies (bootstrap de apps nativas).
    Route::post('device/login', [Auth\DeviceTokenController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth')->group(function (): void {
        Route::get('me', [Auth\MeController::class, 'show']);
        Route::get('menu', [Auth\MeController::class, 'menu']);
        Route::post('logout', Auth\LogoutController::class);
        Route::post('change-password', [Auth\MeController::class, 'changePassword']);
        Route::post('device', [Auth\DeviceTokenController::class, 'store']);
    });

    // Gestión de devices: con sesión (panel) o bearer token (app nativa);
    // el guard sanctum cubre ambos caminos.
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('devices', [Auth\DeviceTokenController::class, 'index']);
        Route::delete('devices/{device}', [Auth\DeviceTokenController::class, 'destroy']);
    });
});

Route::prefix('admin')->middleware(['auth', 'permission:list-admin'])->group(function (): void {
    Route::get('dashboard', Admin\DashboardController::class);

    Route::get('users', [Admin\UserController::class, 'index'])->middleware('permission:list-users');
    Route::post('users', [Admin\UserController::class, 'store'])->middleware('permission:create-users');
    Route::get('users/{user}', [Admin\UserController::class, 'show'])->middleware('permission:list-users');
    Route::put('users/{user}', [Admin\UserController::class, 'update'])->middleware('permission:edit-users');
    Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->middleware('permission:delete-users');

    Route::get('roles', [Admin\RoleController::class, 'index'])->middleware('permission:list-roles');
    Route::post('roles', [Admin\RoleController::class, 'store'])->middleware('permission:create-roles');
    Route::put('roles/{role}', [Admin\RoleController::class, 'update'])->middleware('permission:edit-roles');
    Route::delete('roles/{role}', [Admin\RoleController::class, 'destroy'])->middleware('permission:delete-roles');
    Route::get('permissions', [Admin\RoleController::class, 'permissions'])->middleware('permission:list-roles');

    Route::get('audit', [Admin\AuditController::class, 'index'])->middleware('permission:list-audit');
    Route::get('audit/{activity}', [Admin\AuditController::class, 'show'])->middleware('permission:list-audit');

    Route::get('media', [Admin\MediaController::class, 'index'])->middleware('permission:list-media');
    Route::post('media', [Admin\MediaController::class, 'store'])->middleware('permission:list-media');
    Route::put('media/{media}', [Admin\MediaController::class, 'update'])->middleware('permission:list-media');
    Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->middleware('permission:delete-media');

    Route::get('settings', [Admin\SettingController::class, 'index'])->middleware('permission:manage-settings');
    Route::put('settings', [Admin\SettingController::class, 'update'])->middleware('permission:manage-settings');

    // Definición de custom models (builder) y sus permisos derivados.
    Route::get('models', [Admin\CustomModelController::class, 'index'])->middleware('permission:list-custom-models');
    Route::post('models', [Admin\CustomModelController::class, 'store'])->middleware('permission:create-custom-models');
    Route::put('models/{customModel:slug}', [Admin\CustomModelController::class, 'update'])->middleware('permission:edit-custom-models');
    Route::delete('models/{customModel:slug}', [Admin\CustomModelController::class, 'destroy'])->middleware('permission:delete-custom-models');

    // Campos del builder (materializan columnas reales en la tabla del modelo).
    Route::post('models/{customModel:slug}/fields', [Admin\CustomModelController::class, 'storeField'])->middleware('permission:edit-custom-models');
    Route::put('fields/{field}', [Admin\CustomModelController::class, 'updateField'])->middleware('permission:edit-custom-models');
    Route::delete('fields/{field}', [Admin\CustomModelController::class, 'destroyField'])->middleware('permission:edit-custom-models');

    // Eventos
    Route::get('events', [Admin\EventController::class, 'index'])->middleware('permission:list-events');
    Route::post('events', [Admin\EventController::class, 'store'])->middleware('permission:create-events');
    Route::get('events/{event}', [Admin\EventController::class, 'show'])->middleware('permission:list-events');
    Route::put('events/{event}', [Admin\EventController::class, 'update'])->middleware('permission:edit-events');
    Route::delete('events/{event}', [Admin\EventController::class, 'destroy'])->middleware('permission:delete-events');

    Route::get('bookings', [Admin\BookingController::class, 'index'])->middleware('permission:list-bookings');
    Route::put('bookings/{booking}', [Admin\BookingController::class, 'update'])->middleware('permission:edit-bookings');
    Route::delete('bookings/{booking}', [Admin\BookingController::class, 'destroy'])->middleware('permission:delete-bookings');

    // Tienda
    Route::get('products', [Admin\ProductController::class, 'index'])->middleware('permission:list-products');
    Route::post('products', [Admin\ProductController::class, 'store'])->middleware('permission:create-products');
    Route::get('products/{product}', [Admin\ProductController::class, 'show'])->middleware('permission:list-products');
    Route::put('products/{product}', [Admin\ProductController::class, 'update'])->middleware('permission:edit-products');
    Route::delete('products/{product}', [Admin\ProductController::class, 'destroy'])->middleware('permission:delete-products');

    Route::get('orders', [Admin\OrderController::class, 'index'])->middleware('permission:list-orders');
    Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->middleware('permission:list-orders');
    Route::patch('orders/{order}/status', [Admin\OrderController::class, 'changeStatus'])->middleware('permission:edit-orders');
    Route::post('orders/{order}/refund', [Admin\OrderController::class, 'refund'])->middleware('permission:refund-orders');

    Route::get('coupons', [Admin\CouponController::class, 'index'])->middleware('permission:list-coupons');
    Route::post('coupons', [Admin\CouponController::class, 'store'])->middleware('permission:create-coupons');
    Route::put('coupons/{coupon}', [Admin\CouponController::class, 'update'])->middleware('permission:edit-coupons');
    Route::delete('coupons/{coupon}', [Admin\CouponController::class, 'destroy'])->middleware('permission:delete-coupons');

    Route::get('shipping-methods', [Admin\ShippingMethodController::class, 'index'])->middleware('permission:list-orders');
    Route::post('shipping-methods', [Admin\ShippingMethodController::class, 'store'])->middleware('permission:edit-orders');
    Route::put('shipping-methods/{shippingMethod}', [Admin\ShippingMethodController::class, 'update'])->middleware('permission:edit-orders');
    Route::delete('shipping-methods/{shippingMethod}', [Admin\ShippingMethodController::class, 'destroy'])->middleware('permission:edit-orders');
});

/*
 * Custom Models (contenido dinámico): CRUD genérico por slug.
 * Los permisos por modelo los genera el builder: list-cm-{slug}, etc.
 */
Route::prefix('cm/{customModel}')->middleware(['auth', 'resolve_custom_model'])->group(function (): void {
    Route::get('schema', [CustomModelEntryController::class, 'schema']);
    Route::get('/', [CustomModelEntryController::class, 'index']);
    Route::post('/', [CustomModelEntryController::class, 'store']);
    Route::get('{id}', [CustomModelEntryController::class, 'show']);
    Route::put('{id}', [CustomModelEntryController::class, 'update']);
    Route::delete('{id}', [CustomModelEntryController::class, 'destroy']);
});

/*
 * Storefront API (headless): catálogo, carrito, checkout y reservas.
 * Pensado para la web pública; el panel no lo consume.
 */
Route::prefix('store')->group(function (): void {
    Route::get('products', [Store\ProductController::class, 'index']);
    Route::get('products/{product:slug}', [Store\ProductController::class, 'show']);
    Route::get('events', [Store\EventController::class, 'index']);
    Route::get('events/{event:slug}', [Store\EventController::class, 'show']);

    Route::get('cart', [Store\CartController::class, 'show']);
    Route::post('cart/items', [Store\CartController::class, 'addItem']);
    Route::patch('cart/items/{cartItem}', [Store\CartController::class, 'updateItem']);
    Route::delete('cart/items/{cartItem}', [Store\CartController::class, 'removeItem']);
    Route::post('cart/coupon', [Store\CartController::class, 'applyCoupon']);
    Route::delete('cart/coupon', [Store\CartController::class, 'removeCoupon']);
    Route::post('cart/shipping', [Store\CartController::class, 'setShipping']);

    Route::post('checkout', [Store\CheckoutController::class, 'store']);
    Route::get('orders/{number}', [Store\OrderController::class, 'show']);

    Route::post('bookings', [Store\BookingController::class, 'store']);
});

/*
 * Sincronización (apps nativas): manifiesto y pull de deltas. Requiere
 * token de device con ability `sync` (o sesión de panel para uso manual).
 */
Route::prefix('sync')->middleware(['auth:sanctum', 'abilities:sync'])->group(function (): void {
    Route::get('manifest', [Sync\SyncController::class, 'manifest']);
    Route::post('pull', [Sync\SyncController::class, 'pull'])->middleware('throttle:sync');
    Route::post('push', [Sync\SyncController::class, 'push'])->middleware('throttle:sync');

    // Canal de ficheros de media (central ↔ device).
    Route::post('media/check', [Sync\SyncMediaController::class, 'check']);
    Route::post('media/{uuid}', [Sync\SyncMediaController::class, 'upload']);
    Route::get('media/{uuid}/download', [Sync\SyncMediaController::class, 'download']);
});

/*
 * Sync local del device para la UI del panel (sesión de cookies, BD sqlite
 * embebida). En el central estas rutas responden "no aplica".
 */
Route::prefix('sync')->middleware('auth')->group(function (): void {
    Route::get('state', [Sync\SyncStateController::class, 'state']);
    Route::post('run', [Sync\SyncStateController::class, 'run']);
});

Route::post('webhooks/{provider}', WebhookController::class);
