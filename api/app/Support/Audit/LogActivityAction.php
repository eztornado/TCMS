<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Actions\LogActivityAction as BaseAction;

/**
 * activitylog v5 deja los cambios en `attribute_changes`; esta action los
 * normaliza al contrato clásico `properties.attributes` / `properties.old`
 * que consumen el endpoint de auditoría y el panel (diff old/new).
 */
class LogActivityAction extends BaseAction
{
    protected function transformChanges(Model $activity): void
    {
        $changes = $activity->attribute_changes;

        if (! $changes || $changes->isEmpty()) {
            return;
        }

        $properties = collect($activity->properties ?? []);

        foreach (['attributes', 'old'] as $key) {
            if ($changes->has($key)) {
                $properties->put($key, $changes->get($key));
            }
        }

        $activity->properties = $properties;
        $activity->attribute_changes = null;
    }
}
