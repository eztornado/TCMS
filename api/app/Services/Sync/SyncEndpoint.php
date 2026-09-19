<?php

namespace App\Services\Sync;

use App\Models\Device;
use App\Models\SyncConflict;
use App\Models\SyncLog;
use App\Models\SyncTombstone;
use App\Models\User;
use App\Support\Sync\SyncPayload;
use App\Support\Sync\SyncRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lado CENTRAL de la sincronización: sirve el manifiesto y los deltas de
 * sync_log por cursor de device. Es consumido por SyncController (HTTP) y
 * por LoopbackSyncGateway (tests, en memoria).
 */
final class SyncEndpoint
{
    public function __construct(private readonly ConflictResolver $resolver) {}

    public function manifest(): array
    {
        return [
            'version' => 1,
            'tables' => SyncRegistry::tables(),
            'pivots' => SyncRegistry::pivots(),
            'dynamic_tables' => SyncRegistry::dynamicTables(),
        ];
    }

    /**
     * Delta de cambios desde el último cursor del device. Los cambios hechos
     * por el propio device (su push) se le ocultan. Al drenar el log el
     * cursor salta al último id emitido, aunque sean cambios propios.
     *
     * @return array{changes: array<int, array{table: string, uuid: string, op: string, payload: ?array}>, pivots: array<string, array<int, array<string, mixed>>>, cursor: int, has_more: bool}
     */
    public function pull(?User $user, Device $device, int $limit = 500): array
    {
        $rows = SyncLog::query()
            // El modelo recién creado no hidrata los defaults de la tabla.
            ->where('id', '>', (int) ($device->last_pull_cursor ?? 0))
            ->where(fn ($query) => $query
                ->whereNull('origin_device_uuid')
                ->orWhere('origin_device_uuid', '!=', $device->uuid))
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $cursor = (int) ($rows->max('id') ?? $device->last_pull_cursor);
        if (! $hasMore) {
            $cursor = max($cursor, (int) (SyncLog::query()->max('id') ?? 0));
        }

        $device->forceFill([
            'last_pull_cursor' => $cursor,
            'last_synced_at' => now(),
        ])->save();

        return [
            'changes' => $rows->map(fn (SyncLog $row) => [
                'table' => $row->table_name,
                'uuid' => $row->uuid,
                'op' => $row->op,
                'payload' => $row->payload,
            ])->all(),
            'pivots' => $this->pivotsSnapshot(),
            'cursor' => $cursor,
            'has_more' => $hasMore,
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function pivotsSnapshot(): array
    {
        $snapshot = [];

        foreach (SyncRegistry::pivots() as $table => $pivot) {
            $snapshot[$table] = DB::table($table)
                ->get()
                ->map(fn ($row) => SyncPayload::serializePivotRow((array) $row, $pivot))
                ->all();
        }

        return $snapshot;
    }

    /**
     * Lado central del push: aplica los cambios del device con LWW. Los
     * aceptados se publican en sync_log con origin_device_uuid (los demás
     * devices los descargarán; el autor no se los re-sirve).
     *
     * @param  array<int, array{table: string, uuid: string, op: string, payload?: ?array}>  $changes
     * @return array{results: array<int, array{uuid: string, status: string, reason?: string}>}
     */
    public function push(?User $user, Device $device, array $changes): array
    {
        $results = [];

        foreach ($changes as $change) {
            $results[] = $this->applyPushedChange($device, $change);
        }

        return ['results' => $results];
    }

    /** @param array{table?: mixed, uuid?: mixed, op?: mixed, payload?: mixed} $change */
    private function applyPushedChange(Device $device, array $change): array
    {
        $table = (string) ($change['table'] ?? '');
        $uuid = (string) ($change['uuid'] ?? '');

        if (! SyncRegistry::isSyncable($table)) {
            return ['uuid' => $uuid, 'status' => 'rejected', 'reason' => 'tabla_no_syncable'];
        }

        if (SyncRegistry::policyOf($table) !== 'bidi') {
            return ['uuid' => $uuid, 'status' => 'rejected', 'reason' => 'tabla_pull_only'];
        }

        if (($change['op'] ?? '') === 'delete') {
            return $this->applyPushedDelete($device, $table, $uuid);
        }

        return $this->applyPushedUpsert($device, $table, $uuid, (array) ($change['payload'] ?? []));
    }

    /** @param array<string, mixed> $payload */
    private function applyPushedUpsert(Device $device, string $table, string $uuid, array $payload): array
    {
        // El payload viaja con FKs como uuid (formato cable): para escribir en
        // el central se resuelven a ids locales; al log se registra el wire.
        $refs = SyncRegistry::tables()[$table]['refs']
            ?? SyncRegistry::dynamicDefinition($table)['refs'];

        $row = SyncPayload::deserialize($payload, $refs);
        $row['uuid'] = $uuid;
        $row = array_intersect_key($row, array_flip(Schema::getColumnListing($table)));

        $central = DB::table($table)->where('uuid', $uuid)->first();

        if ($central !== null
            && ! $this->resolver->deviceChangeWins(
                $this->stringValue($row['updated_at'] ?? null),
                $this->stringValue($central->updated_at ?? null),
            )) {
            SyncConflict::create([
                'table_name' => $table,
                'uuid' => $uuid,
                'origin_device_uuid' => $device->uuid,
                'local_updated_at' => $row['updated_at'] ?? null,
                'central_updated_at' => $central->updated_at,
                'central_payload' => (array) $central,
            ]);

            return ['uuid' => $uuid, 'status' => 'conflict'];
        }

        if ($central === null) {
            DB::table($table)->insert($row);
        } else {
            DB::table($table)->where('id', $central->id)->update($row);
        }

        $this->record($device, $table, $uuid, 'upsert', $payload);

        return ['uuid' => $uuid, 'status' => 'applied'];
    }

    private function applyPushedDelete(Device $device, string $table, string $uuid): array
    {
        $exists = DB::table($table)->where('uuid', $uuid)->exists();

        if ($exists) {
            DB::table($table)->where('uuid', $uuid)->delete();

            $this->record($device, $table, $uuid, 'delete', null);

            SyncTombstone::create([
                'table_name' => $table,
                'uuid' => $uuid,
                'origin_device_uuid' => $device->uuid,
                'deleted_at' => now(),
            ]);
        }

        return ['uuid' => $uuid, 'status' => 'applied'];
    }

    private function record(Device $device, string $table, string $uuid, string $op, ?array $payload): void
    {
        SyncLog::create([
            'table_name' => $table,
            'uuid' => $uuid,
            'op' => $op,
            'payload' => $payload,
            'origin_device_uuid' => $device->uuid,
        ]);
    }

    private function stringValue(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
