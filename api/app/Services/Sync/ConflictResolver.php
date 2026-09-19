<?php

namespace App\Services\Sync;

/**
 * Resolución de conflictos LWW (last-write-wins): un cambio del device solo
 * sobrescribe el central si su updated_at es ESTRICTAMENTE más reciente.
 * Empate o más antiguo: gana el central (fuente de verdad), el device
 * converge en el siguiente pull y el choque queda en sync_conflicts.
 */
final class ConflictResolver
{
    /** Los timestamps viajan en formato de BD (Y-m-d H:i:s): comparable léxico. */
    public function deviceChangeWins(?string $incomingUpdatedAt, ?string $centralUpdatedAt): bool
    {
        if ($centralUpdatedAt === null || $centralUpdatedAt === '') {
            return true;
        }

        if ($incomingUpdatedAt === null || $incomingUpdatedAt === '') {
            return false;
        }

        return $incomingUpdatedAt > $centralUpdatedAt;
    }
}
