<?php

namespace App\Support\Schedule;

use App\Models\StockReservation;
use App\Models\WebhookEvent;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\Activitylog\Models\Activity;

/**
 * Tareas programadas del core, compartidas por todos los runtimes:
 * web (routes/console.php, schedule:work) y nativo (catch-up al arrancar).
 * Los módulos de proyecto pueden añadir las suyas con `Schedule::command(...)`.
 */
final class TcmsSchedule
{
    public static function register(Schedule $schedule): void
    {
        // Expiración de reservas de stock de carritos abandonados (cada 5 minutos).
        $schedule->call(function () {
            StockReservation::query()->where('expires_at', '<', now())->delete();
        })->everyFiveMinutes()->name('shop:prune-stock-reservations')->withoutOverlapping();

        // Limpieza de eventos de webhook procesados hace más de 30 días.
        $schedule->call(function () {
            WebhookEvent::query()->where('created_at', '<', now()->subDays(30))->delete();
        })->dailyAt('03:40')->name('shop:prune-webhook-events')->withoutOverlapping();

        // Auditoría: el activity log de spatie crece sin límite; se mantiene 1 año.
        $schedule->command('model:prune')->daily();
        $schedule->call(function () {
            Activity::query()->where('created_at', '<', now()->subYear())->delete();
        })->weekly()->sundays()->at('02:10')->name('audit:prune-year-old')->withoutOverlapping();
    }
}
