<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Sync\PullService;
use App\Services\Sync\PushService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Pull del device contra el central. Identidad del device: opciones o
 * config tcms.sync (TCMS_SYNC_DEVICE_UUID / TCMS_SYNC_TOKEN). En el binario
 * nativo los guarda la app al enlazarlo; en producción lo lanza el schedule.
 */
#[Signature('sync:run {--device= : Uuid del device (o TCMS_SYNC_DEVICE_UUID)} {--token= : Token de device con ability sync}')]
#[Description('Sincroniza el device con el API central (push del outbox + pull de deltas)')]
class SyncRunCommand extends Command
{
    public function handle(PullService $pull, PushService $push): int
    {
        $device = $this->resolveDevice();

        // Primero el push local (outbox), después el pull: los conflictos
        // quedan convergidos con la versión del central.
        $summary = $push->sync($device);

        if ($summary['pushed'] > 0) {
            $this->line(sprintf(
                'Push: %d enviados, %d aplicados, %d conflictos, %d rechazados.',
                $summary['pushed'],
                $summary['applied'],
                $summary['conflicts'],
                $summary['rejected'],
            ));
        }

        $response = $pull->pull($device);

        $applied = collect($response['changes'] ?? [])
            ->groupBy('table')
            ->map->count();

        $this->info('Cursor: '.$response['cursor']);

        if ($applied->isEmpty()) {
            $this->line('Sin cambios pendientes.');

            return self::SUCCESS;
        }

        foreach ($applied as $table => $count) {
            $this->line(sprintf('  %-28s %d cambio(s)', $table, $count));
        }

        if ($response['has_more'] ?? false) {
            $this->warn('Quedan más cambios: ejecuta sync:run de nuevo.');
        }

        return self::SUCCESS;
    }

    private function resolveDevice(): Device
    {
        $uuid = $this->option('device') ?: config('tcms.sync.device_uuid');

        if (((string) $uuid) === '') {
            throw new RuntimeException('Device no identificado: usa --device o TCMS_SYNC_DEVICE_UUID.');
        }

        $token = $this->option('token') ?: config('tcms.sync.token');

        if (((string) $token) === '' && ! app()->runningUnitTests()) {
            throw new RuntimeException('Token de device no configurado: usa --token o TCMS_SYNC_TOKEN.');
        }

        // Contexto local del device (la fila local se completa al enlazar).
        return Device::query()->firstOrCreate(
            ['uuid' => $uuid],
            ['label' => (string) config('tcms.sync.device_label', 'Device local')],
        );
    }
}
