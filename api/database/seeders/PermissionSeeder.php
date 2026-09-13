<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catálogo de permisos con la convención {verbo}-{recurso} de reigreengroup
 * (list/show/create/edit/delete + capacidades manage/export). Es la lista
 * cerrada que el editor de roles muestra al admin.
 */
class PermissionSeeder extends Seeder
{
    /** [recurso => [verbos extra a los 5 canónicos]] */
    public const RESOURCES = [
        'admin' => [],
        'users' => [],
        'roles' => [],
        'audit' => [],
        'media' => ['delete'],
        'settings' => ['manage'],
        'custom-models' => ['create', 'edit', 'delete'],
        'events' => [],
        'bookings' => ['edit', 'delete'],
        'products' => [],
        'orders' => ['edit', 'refund'],
        'coupons' => ['create', 'edit', 'delete'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [];

        foreach (self::RESOURCES as $resource => $extraVerbs) {
            foreach (['list', 'show', 'create', 'edit', 'delete', ...$extraVerbs] as $verb) {
                $permissions[] = "$verb-$resource";
            }
        }

        foreach (array_unique($permissions) as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        $admin = Role::firstOrCreate(['name' => 'Admin', 'label' => 'Administrador']);
        $admin->description = 'Acceso total al panel';
        $admin->givePermissionTo(Permission::all());

        $editor = Role::firstOrCreate(['name' => 'Editor', 'label' => 'Editor de contenido']);
        $editor->givePermissionTo([
            'list-admin', 'list-media', 'list-custom-models', 'create-custom-models',
            'edit-custom-models', 'delete-custom-models',
            'list-events', 'create-events', 'edit-events', 'delete-events',
            'list-bookings', 'edit-bookings',
            'list-products', 'create-products', 'edit-products',
        ]);

        $shopManager = Role::firstOrCreate(['name' => 'Gestor de tienda', 'label' => 'Gestor de tienda']);
        $shopManager->givePermissionTo([
            'list-admin', 'list-media', 'list-products', 'create-products',
            'edit-products', 'delete-products', 'list-orders', 'edit-orders',
            'refund-orders', 'list-coupons', 'create-coupons', 'edit-coupons',
            'delete-coupons',
        ]);
    }
}
