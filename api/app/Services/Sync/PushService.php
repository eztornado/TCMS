<?php

namespace App\Services\Sync;

use App\Models\Device;
use App\Models\SyncOutbox;
use App\Services\Sync\Contracts\SyncGateway;

/**
 * Lado DEVICE del push: envía el outbox local al central y limpia lo
 * aceptado. En conflicto o rechazo la fila del outbox se elimina igualmente:
 * el siguiente pull converge la copia local hacia el central (que gana).
 */
final class PushService
{
    public function __construct(
        private readonly SyncGateway $gateway,
        private readonly PullService $pullService,
        private readonly MediaFileSyncService $mediaFiles,
    ) {}

    /**
     * Ciclo completo de sincronización: outbox → central, ficheros subidos,
     * pull de deltas y ficheros faltantes. Los conflictos quedan convergidos
     * con la versión del central (fuente de verdad).
     *
     * @return array{pushed: int, applied: int, conflicts: int, rejected: int, files_uploaded: int, files_downloaded: int, cursor: int}
     */
    public function sync(Device $device, int $batchSize = 500): array
    {
        $summary = $this->push($device, $batchSize);

        $summary['files_uploaded'] = $this->mediaFiles->pushFiles($device);

        $response = $this->pullService->pull($device);

        $summary['files_downloaded'] = $this->mediaFiles->pullFiles($device);
        $summary['cursor'] = $response['cursor'];

        return $summary;
    }

    /** @return array{pushed: int, applied: int, conflicts: int, rejected: int, cursor: int} */
    public function push(Device $device, int $batchSize = 500): array
    {
        $pending = SyncOutbox::query()->orderBy('id')->limit($batchSize)->get();

        $summary = ['pushed' => $pending->count(), 'applied' => 0, 'conflicts' => 0, 'rejected' => 0, 'cursor' => 0];

        if ($pending->isEmpty()) {
            return $summary;
        }

        $changes = $pending->map(fn (SyncOutbox $row) => [
            'table' => $row->table_name,
            'uuid' => $row->uuid,
            'op' => $row->op,
            'payload' => $row->payload,
        ])->all();

        $results = collect($this->gateway->push($device, $changes)['results'] ?? []);

        $summary['applied'] = $results->where('status', 'applied')->count();
        $summary['conflicts'] = $results->where('status', 'conflict')->count();
        $summary['rejected'] = $results->where('status', 'rejected')->count();

        // Ack: las filas enviadas salen del outbox (aplicadas o no: el pull
        // converge el estado local con el central, que es la fuente de verdad).
        SyncOutbox::query()->whereIn('id', $pending->pluck('id'))->delete();

        return $summary;
    }
}
