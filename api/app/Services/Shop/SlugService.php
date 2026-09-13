<?php

namespace App\Services\Shop;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Generación de slugs únicos (en rotary el slug era duplicable). */
class SlugService
{
    public static function from(string $source, string $table, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        $counter = 1;

        while (DB::table($table)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.++$counter;
        }

        return $slug;
    }
}
