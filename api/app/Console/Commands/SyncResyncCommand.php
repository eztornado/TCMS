<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Sync\PullService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Resincronización completa: resetea el cursor del device y re-aplica todo el
 * sync_log (dentro de su retención de 30 días). Útil tras corrupción local
 * o si el device quedó fuera de rango de cursor.
 */
#[Signature('sync:resync {--device= : Uuid del device (o TCMS_SYNC_DEVICE_UUID)}')]
#[Description('Resincroniza el device desde cero (repite todo el sync_log)')]
class SyncResyncCommand extends Command
{
    public function handle(PullService $pull): int
    {
        $uuid = $this->option('device') ?: config('tcms.sync.device_uuid');

        if (((string) $uuid) === '') {
            $this->error('Device no identificado: usa --device o TCMS_SYNC_DEVICE_UUID.');

            return self::FAILURE;
        }

        $device = Device::query()->firstWhere('uuid', $uuid);

        if ($device === null) {
            $this->error('Device no registrado localmente.');

            return self::FAILURE;
        }

        $device->forceFill(['last_pull_cursor' => 0])->save();
        $this->info('Cursor reseteado: re-aplicando el log completo...');

        $total = 0;
        $applied = collect();

        do {
            $response = $pull->pull($device->fresh());
            $batch = collect($response['changes'] ?? []);
            $total += $batch->count();
            $applied = $applied->merge($batch->pluck('table'));
            $this->line(sprintf('  Lote de %d cambio(s)...', $batch->count()));
        } while ($response['has_more'] ?? false);

        $this->info("Resync completado: {$total} cambios re-aplicados (cursor {$response['cursor']}).");

        return self::SUCCESS;
    }
}
