<?php

namespace App\Services\Sync;

use App\Models\CustomModel;
use App\Models\Device;
use App\Services\CustomModels\SchemaService;
use App\Services\Sync\Contracts\SyncGateway;
use App\Support\Sync\SyncPayload;
use App\Support\Sync\SyncRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lado DEVICE del pull: descarga deltas del central vía gateway y los aplica
 * a la base local. Los cambios aplicados NO se re-capturan (flag del observer)
 * y viajan con sus timestamps originales (necesario para LWW en el push).
 *
 * Aplica por DB builder, no por Eloquent, a propósito: sin casts, sin
 * auditoría y sin eventos en el apply — el device reproduce exactamente
 * lo que hay en el central.
 */
final class PullService
{
    private bool $dynamicSchemaEnsured = false;

    public function __construct(
        private readonly SyncGateway $gateway,
        private readonly SchemaService $schema,
    ) {}

    /** Descarga y aplica el delta pendiente del device. */
    public function pull(Device $device): array
    {
        $response = $this->gateway->pull($device);
        $this->apply($response);

        return $response;
    }

    /** Aplica una respuesta de pull (changes + pivots) a la base local. */
    public function apply(array $response): void
    {
        SyncObserver::$applying = true;
        $this->dynamicSchemaEnsured = false;

        try {
            DB::transaction(function () use ($response): void {
                $this->applyChanges($response['changes'] ?? []);
                $this->applyPivots($response['pivots'] ?? []);
            });

            // El catálogo de roles/permisos acaba de cambiar.
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } finally {
            SyncObserver::$applying = false;
        }
    }

    /** @param array<int, array{table: string, uuid: string, op: string, payload: ?array}> $changes */
    private function applyChanges(array $changes): void
    {
        $ordered = $this->sortByApplyOrder($changes);

        foreach ($ordered as $change) {
            if ($change['op'] === 'delete') {
                $this->applyDelete($change);

                continue;
            }

            $this->applyUpsert($change);
        }
    }

    private function applyUpsert(array $change): void
    {
        $table = $change['table'];
        $this->ensureDynamicSchema($table);

        $refs = $this->refsFor($table);
        $payload = SyncPayload::deserialize((array) $change['payload'], $refs);
        $payload['uuid'] = $change['uuid'];

        // Solo columnas que existen localmente (tolera drift de esquema).
        $columns = array_flip(Schema::getColumnListing($table));
        $payload = array_intersect_key($payload, $columns);

        $localId = DB::table($table)->where('uuid', $change['uuid'])->value('id');

        if ($localId !== null) {
            DB::table($table)->where('id', $localId)->update($payload);

            return;
        }

        DB::table($table)->insert($payload);
    }

    private function applyDelete(array $change): void
    {
        $table = $change['table'];

        if ($table === 'custom_models') {
            $model = CustomModel::query()
                ->where('uuid', $change['uuid'])
                ->first();

            if ($model && Schema::hasTable($model->table_name)) {
                $this->schema->dropTable($model);
            }
        }

        // Una tabla dinámica que no existe localmente no tiene nada que borrar.
        if (str_starts_with($table, 'cm_') && ! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->where('uuid', $change['uuid'])->delete();
    }

    /** Snapshot completo: el estado central de los pivots sustituye al local. */
    private function applyPivots(array $pivots): void
    {
        foreach ($pivots as $table => $rows) {
            $pivot = SyncRegistry::pivots()[$table];

            if ($pivot === null) {
                continue;
            }

            DB::table($table)->delete();

            foreach ($rows as $row) {
                DB::table($table)->insert(
                    SyncPayload::deserializePivotRow((array) $row, $pivot),
                );
            }
        }
    }

    /** Definición → tabla física local, antes de tocar filas dinámicas. */
    private function ensureDynamicSchema(string $table): void
    {
        if ($this->dynamicSchemaEnsured || ! str_starts_with($table, 'cm_')) {
            return;
        }

        foreach (CustomModel::query()->with('fields')->get() as $customModel) {
            if (! Schema::hasTable($customModel->table_name)) {
                $this->schema->createTable($customModel);

                continue;
            }

            foreach ($customModel->fields as $field) {
                if (! Schema::hasColumn($customModel->table_name, $field->name)) {
                    $this->schema->addColumn($customModel, $field);
                }
            }
        }

        $this->dynamicSchemaEnsured = true;
    }

    private function refsFor(string $table): array
    {
        return SyncRegistry::tables()[$table]['refs']
            ?? SyncRegistry::dynamicDefinition($table)['refs'];
    }

    /**
     * Orden de aplicación: definiciones y catálogos primero, entidades
     * después, dinámicas a continuación (tras materializar su esquema) y
     * tablas desconocidas al final.
     */
    private function sortByApplyOrder(array $changes): array
    {
        $order = array_flip(SyncRegistry::applyOrder());
        $dynamicIndex = count($order);

        usort($changes, function (array $a, array $b) use ($order, $dynamicIndex): int {
            return $this->rank($a['table'], $order, $dynamicIndex)
                <=> $this->rank($b['table'], $order, $dynamicIndex);
        });

        return $changes;
    }

    /** @param array<string, int> $order */
    private function rank(string $table, array $order, int $dynamicIndex): int
    {
        if (isset($order[$table])) {
            return $order[$table];
        }

        return str_starts_with($table, 'cm_') ? $dynamicIndex : $dynamicIndex + 1;
    }
}
