<?php

namespace App\Services\Sync;

use App\Models\SyncLog;
use App\Models\SyncOutbox;
use App\Models\SyncTombstone;
use App\Support\Runtime;
use App\Support\Sync\SyncPayload;
use App\Support\Sync\SyncRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Captura cambios de las tablas syncables.
 *
 *  - En el central (web): escribe sync_log (+ tombstone en borrados). Ese log
 *    es lo que los devices descargan por cursor.
 *  - En el device (nativo): los cambios locales van al outbox (F4) para su
 *    push; el pull que aplica cambios del central no se re-captura (flag).
 */
class SyncObserver
{
    /** El device está aplicando cambios llegados por pull: no re-loguear. */
    public static bool $applying = false;

    /** Uuid estable para modelos sin el trait Syncable (Role, Permission...). */
    public function creating(Model $model): void
    {
        if (! $model->uuid) {
            $model->uuid = (string) Str::uuid();
        }
    }

    public function created(Model $model): void
    {
        $this->log($model, 'upsert');
    }

    public function updated(Model $model): void
    {
        $this->log($model, 'upsert');
    }

    public function restored(Model $model): void
    {
        $this->log($model, 'upsert');
    }

    public function deleted(Model $model): void
    {
        // Soft delete: la fila sigue existiendo en la base (deleted_at), así
        // que viaja como upsert y el device replica el mismo estado. Solo el
        // forceDelete es un delete real con tombstone.
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)
            && ! $model->isForceDeleting()) {
            $this->log($model, 'upsert');

            return;
        }

        $this->log($model, 'delete');
    }

    protected function log(Model $model, string $op): void
    {
        $table = $model->getTable();

        if (self::$applying || ! SyncRegistry::isSyncable($table)) {
            return;
        }

        $refs = SyncRegistry::tables()[$table]['refs']
            ?? SyncRegistry::dynamicDefinition($table)['refs'];

        // Device: el cambio local se encola para su push. Central: se
        // publica en el log que los devices descargan.
        if (! Runtime::isWeb()) {
            SyncOutbox::create([
                'table_name' => $table,
                'uuid' => (string) $model->uuid,
                'op' => $op,
                'payload' => $op === 'upsert'
                    ? SyncPayload::serialize($model, $refs)
                    : null,
            ]);

            return;
        }

        SyncLog::create([
            'table_name' => $table,
            'uuid' => (string) $model->uuid,
            'op' => $op,
            'payload' => $op === 'upsert'
                ? SyncPayload::serialize($model, $refs)
                : null,
        ]);

        if ($op === 'delete') {
            SyncTombstone::create([
                'table_name' => $table,
                'uuid' => (string) $model->uuid,
                'deleted_at' => now(),
            ]);
        }
    }
}
