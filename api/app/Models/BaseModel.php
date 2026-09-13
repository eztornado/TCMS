<?php

namespace App\Models;

use App\Support\Concerns\SortsByWhitelist;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Base de todos los modelos de dominio: audita creación, actualización y
 * borrado en el activity log (capa de auditoría de TCMS).
 */
abstract class BaseModel extends Model
{
    use HasFactory, LogsActivity, SortsByWhitelist;

    protected string $defaultSortColumn = 'id';

    protected string $defaultSortDirection = 'desc';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'creado',
                'updated' => 'actualizado',
                'deleted' => 'eliminado',
                default => $eventName,
            });
    }
}
