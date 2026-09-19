<?php

namespace App\Support\Sync;

use App\Models\CustomModel;
use App\Models\CustomModelField;
use App\Models\DynamicEntry;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Taxonomy;
use App\Models\TaxRate;
use App\Models\Term;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Manifiesto de sincronización: qué tablas viajan entre central y devices,
 * con qué política y cómo se resuelven sus FK (por uuid de la tabla destino).
 *
 *  - pull:  solo lectura en el device (identidad, configuración, catálogo).
 *  - bidi:  bidireccional con resolución LWW de conflictos.
 *  - pivot: snapshot completo en cada pull (tablas pequeñas, sin uuid:
 *           identidad compuesta; los ids viajan normalizados a uuid).
 *
 * Nunca sincroniza: sessions, cache, jobs, activity_log, personal_access_tokens,
 * devices, sync_*, comercio transaccional (orders, carts, payments...),
 * que es online-only en v1.
 */
final class SyncRegistry
{
    /** @return array<string, array{model: class-string, policy: string, refs: array<string, string>}> */
    public static function tables(): array
    {
        return [
            // La definición antes que las entradas: el device materializa su
            // esquema local antes de aplicar filas de tablas dinámicas.
            'custom_models' => ['model' => CustomModel::class, 'policy' => 'pull', 'refs' => ['created_by' => 'users']],
            'custom_model_fields' => ['model' => CustomModelField::class, 'policy' => 'pull', 'refs' => ['custom_model_id' => 'custom_models']],

            'users' => ['model' => User::class, 'policy' => 'pull', 'refs' => []],
            'roles' => ['model' => Role::class, 'policy' => 'pull', 'refs' => []],
            'permissions' => ['model' => Permission::class, 'policy' => 'pull', 'refs' => []],

            'settings' => ['model' => Setting::class, 'policy' => 'pull', 'refs' => []],
            'menus' => ['model' => Menu::class, 'policy' => 'pull', 'refs' => []],
            'menu_items' => ['model' => MenuItem::class, 'policy' => 'pull', 'refs' => ['menu_id' => 'menus', 'parent_id' => 'menu_items']],

            'taxonomies' => ['model' => Taxonomy::class, 'policy' => 'pull', 'refs' => []],
            'terms' => ['model' => Term::class, 'policy' => 'pull', 'refs' => ['taxonomy_id' => 'taxonomies', 'parent_id' => 'terms']],

            'tax_rates' => ['model' => TaxRate::class, 'policy' => 'pull', 'refs' => []],
            // Media es bidi: el device puede subir imágenes offline; los BYTES
            // viajan por el canal propio de MediaFileSyncService.
            'media' => ['model' => Media::class, 'policy' => 'bidi', 'refs' => ['user_id' => 'users']],

            'products' => ['model' => Product::class, 'policy' => 'bidi', 'refs' => ['tax_rate_id' => 'tax_rates', 'user_id' => 'users']],
            'product_variants' => ['model' => ProductVariant::class, 'policy' => 'bidi', 'refs' => ['product_id' => 'products']],
            'events' => ['model' => Event::class, 'policy' => 'bidi', 'refs' => []],
            'event_sessions' => ['model' => EventSession::class, 'policy' => 'bidi', 'refs' => ['event_id' => 'events']],
        ];
    }

    /** Pivots que viajan como snapshot completo en cada pull (ids → uuid). */
    public static function pivots(): array
    {
        return [
            'model_has_roles' => [
                'keys' => ['model_type', 'model_id', 'role_id'],
                'refs' => ['role_id' => 'roles', 'model_id' => 'users'],
                'model_type_tables' => ['user' => 'users'],
            ],
            'model_has_permissions' => [
                'keys' => ['model_type', 'model_id', 'permission_id'],
                'refs' => ['permission_id' => 'permissions', 'model_id' => 'users'],
                'model_type_tables' => ['user' => 'users'],
            ],
            'role_has_permissions' => [
                'keys' => ['role_id', 'permission_id'],
                'refs' => ['role_id' => 'roles', 'permission_id' => 'permissions'],
            ],
        ];
    }

    /** Tablas dinámicas de Custom Models activos (esquema generado). */
    public static function dynamicTables(): array
    {
        return CustomModel::query()->pluck('table_name')->all();
    }

    public static function isSyncable(string $table): bool
    {
        return isset(self::tables()[$table]) || in_array($table, self::dynamicTables(), true);
    }

    /** Definición de una tabla dinámica cm_* (bidi, FK autor -> users). */
    public static function dynamicDefinition(string $table): array
    {
        return ['model' => DynamicEntry::class, 'policy' => 'bidi', 'refs' => ['user_id' => 'users']];
    }

    /** Tablas en orden de aplicación: definición → catálogos → entidades. */
    public static function applyOrder(): array
    {
        return [
            'custom_models', 'custom_model_fields', 'users', 'roles', 'permissions',
            'settings', 'menus', 'menu_items', 'taxonomies', 'terms', 'tax_rates',
            'media', 'products', 'product_variants', 'events', 'event_sessions',
        ];
    }

    /** ¿El uuid de la fila la declara el device (push) o el central? */
    public static function policyOf(string $table): ?string
    {
        return self::tables()[$table]['policy']
            ?? (in_array($table, self::dynamicTables(), true) ? 'bidi' : null);
    }
}
