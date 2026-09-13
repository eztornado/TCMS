<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Ordenación de listados con whitelist de columnas. Nunca se pasa el input
 * del usuario directo a orderBy (en rotary ese era el vector de inyección
 * de columnas). Uso: $query->sortableBy(['id','title'], $request).
 */
trait SortsByWhitelist
{
    public function scopeSortableBy(Builder $query, array $columns, Request $request): Builder
    {
        $sort = $request->query('sort', $this->getDefaultSortColumn());
        $direction = str_starts_with((string) $sort, '-') ? 'desc' : 'asc';
        $column = ltrim((string) $sort, '-');

        if (! in_array($column, $columns, true)) {
            $column = $this->getDefaultSortColumn();
            $direction = $this->getDefaultSortDirection();
        }

        return $query->orderBy($column, $direction);
    }

    public function getDefaultSortColumn(): string
    {
        return $this->defaultSortColumn ?? 'id';
    }

    public function getDefaultSortDirection(): string
    {
        return $this->defaultSortDirection ?? 'desc';
    }
}
