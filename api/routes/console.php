<?php

use App\Models\StockReservation;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Schedule;
use Spatie\Activitylog\Models\Activity;

/*
| Programación de tareas del core. Los módulos de proyecto pueden añadir
| las suyas desde su ServiceProvider con `Schedule::command(...)`.
*/

// Expiración de reservas de stock de carritos abandonados (cada 5 minutos).
Schedule::call(function () {
    StockReservation::query()->where('expires_at', '<', now())->delete();
})->everyFiveMinutes()->name('shop:prune-stock-reservations')->withoutOverlapping();

// Limpieza de eventos de webhook procesados hace más de 30 días.
Schedule::call(function () {
    WebhookEvent::query()->where('created_at', '<', now()->subDays(30))->delete();
})->dailyAt('03:40')->name('shop:prune-webhook-events')->withoutOverlapping();

// Auditoría: el activity log de spatie crece sin límite; se mantiene 1 año.
Schedule::command('model:prune')->daily();
Schedule::call(function () {
    Activity::query()->where('created_at', '<', now()->subYear())->delete();
})->weekly()->sundays()->at('02:10')->name('audit:prune-year-old');
