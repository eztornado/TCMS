<?php

namespace App\Support\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Serialización de filas para el protocolo de sync: atributos crudos sin id
 * local, con las FK sustituidas por el uuid de la fila relacionada. El apply
 * hace la operación inversa contra la base local.
 */
final class SyncPayload
{
    /** Fila → payload portable (FKs como uuid). */
    public static function serialize(Model $model, array $refs): array
    {
        $attributes = $model->getAttributes();
        unset($attributes['id']);

        foreach ($refs as $column => $relatedTable) {
            $localId = $attributes[$column] ?? null;
            $attributes[$column] = $localId === null
                ? null
                : self::uuidOf($relatedTable, (int) $localId);
        }

        return $attributes;
    }

    /** Payload portable → atributos para la base local (uuid → id local). */
    public static function deserialize(array $payload, array $refs): array
    {
        foreach ($refs as $column => $relatedTable) {
            $uuid = $payload[$column] ?? null;
            $payload[$column] = $uuid === null
                ? null
                : self::idOf($relatedTable, (string) $uuid);
        }

        return $payload;
    }

    /** @param array<string, mixed> $row */
    public static function serializePivotRow(array $row, array $pivot): array
    {
        foreach ($pivot['refs'] as $column => $relatedTable) {
            if (isset($row[$column]) && $row[$column] !== null) {
                $row[$column] = self::uuidOf($relatedTable, (int) $row[$column]);
            }
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    public static function deserializePivotRow(array $row, array $pivot): array
    {
        foreach ($pivot['refs'] as $column => $relatedTable) {
            if (isset($row[$column]) && $row[$column] !== null) {
                $row[$column] = self::idOf($relatedTable, (string) $row[$column]);
            }
        }

        return $row;
    }

    private static function uuidOf(string $table, int $id): ?string
    {
        return DB::table($table)->where('id', $id)->value('uuid');
    }

    private static function idOf(string $table, string $uuid): ?int
    {
        $id = DB::table($table)->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }
}
