<?php

namespace App\Providers;

use App\Models\Device;
use App\Services\Sync\PushService;
use App\Support\Schedule\TcmsSchedule;
use Illuminate\Console\Scheduling\Schedule;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // Provider del build de ESCRITORIO (usa nativephp/desktop). El build
        // móvil, al llevar nativephp/mobile (excluyente con desktop en
        // composer), usa su propio scaffold de provider.
        Window::open()
            ->title(config('app.name', 'TornadoCMS'))
            ->rememberState();

        Window::resize(1400, 900);

        $this->catchUpSchedule();
        $this->catchUpSync();
    }

    /**
     * Desktop v2 no trae scheduler propio: al arrancar la app ejecutamos las
     * tareas vencidas del Schedule compartido (TcmsSchedule). Son prunes
     * idempotentes, así que el catch-up por arranque basta en v1.
     */
    protected function catchUpSchedule(): void
    {
        $schedule = app(Schedule::class);

        TcmsSchedule::register($schedule);

        foreach ($schedule->dueEvents(app()) as $event) {
            $event->run(app());
        }
    }

    /**
     * Sincronización al arrancar (y reaprovecha la reapertura de la app como
     * "red disponible"). Si no hay device enlazado ni red, falla en silencio:
     * el panel permite lanzarla manualmente.
     */
    protected function catchUpSync(): void
    {
        if (((string) config('tcms.sync.device_uuid')) === ''
            || ((string) config('tcms.sync.token')) === '') {
            return;
        }

        try {
            app(PushService::class)->sync(
                Device::query()->firstWhere('uuid', config('tcms.sync.device_uuid'))
                    ?? Device::query()->firstOrCreate([
                        'uuid' => (string) config('tcms.sync.device_uuid'),
                    ], ['label' => (string) config('tcms.sync.device_label', 'Device local')]),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
        ];
    }
}
