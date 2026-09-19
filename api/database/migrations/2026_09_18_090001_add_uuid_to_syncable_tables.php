<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Identidad de sincronización: columna uuid en todas las tablas que viajan
 * entre el central y los devices. Los ids enteros se quedan intactos (la web
 * y sus URLs siguen con ellos); el uuid es la clave de negocio estable que
 * sobrevive a bases distintas.
 *
 * Pivots (model_has_*, termgables, mediables) no llevan uuid: se sincronizan
 * como snapshot completo (tablas pequeñas, identidad compuesta).
 */
return new class extends Migration
{
    /** Tablas syncables que aún no tienen uuid. */
    private const TABLES = [
        'users', 'roles', 'permissions', 'settings', 'menus', 'menu_items',
        'taxonomies', 'terms', 'tax_rates', 'custom_models', 'custom_model_fields',
        'products', 'product_variants', 'events', 'event_sessions',
    ];

    public function up(): void
    {
        // Tablas fijas + tablas dinámicas ya materializadas de Custom Models.
        $tables = self::TABLES;

        if (Schema::hasTable('custom_models')) {
            $tables = array_merge($tables, DB::table('custom_models')->pluck('table_name')->all());
        }

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->nullable()->unique();
            });

            // Backfill en lotes: toda fila existente gana su uuid definitivo.
            DB::table($table)
                ->whereNull('uuid')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['uuid' => (string) Str::uuid()]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'uuid')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropUnique(['uuid']);
                    $blueprint->dropColumn('uuid');
                });
            }
        }
    }
};
