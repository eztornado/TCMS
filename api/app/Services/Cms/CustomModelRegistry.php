<?php

namespace App\Services\Cms;

use App\Models\CustomModel;
use Illuminate\Support\Facades\Cache;

/**
 * Acceso a definiciones de custom models con caché. En rotary cada petición
 * hacía ~5 queries de metadatos; aquí 1 query como máximo por slug y TTL.
 */
class CustomModelRegistry
{
    public function find(string $slug): ?CustomModel
    {
        /** @var CustomModel|null $model */
        $model = Cache::remember("cm.$slug", 600, fn () => CustomModel::query()
            ->with('fields')
            ->where('slug', $slug)
            ->first());

        return $model;
    }

    public function flush(string $slug): void
    {
        Cache::forget("cm.$slug");
    }
}
