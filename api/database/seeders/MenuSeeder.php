<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/** Menú del panel. El backend lo sirve ya filtrado por permisos. */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Menu::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Panel de administración']);

        $items = [
            ['label' => 'Panel', 'path' => '/', 'icon' => 'dashboard', 'permission' => 'list-admin'],
            ['label' => 'Usuarios', 'path' => '/users', 'icon' => 'users', 'permission' => 'list-users'],
            ['label' => 'Roles', 'path' => '/roles', 'icon' => 'shield', 'permission' => 'list-roles'],
            ['label' => 'Auditoría', 'path' => '/audit', 'icon' => 'history', 'permission' => 'list-audit'],
            ['label' => 'Contenido', 'path' => '/content', 'icon' => 'layout', 'permission' => 'list-custom-models'],
            ['label' => 'Eventos', 'path' => '/events', 'icon' => 'calendar', 'permission' => 'list-events'],
            ['label' => 'Reservas', 'path' => '/events/bookings', 'icon' => 'ticket', 'permission' => 'list-bookings'],
            ['label' => 'Productos', 'path' => '/shop/products', 'icon' => 'package', 'permission' => 'list-products'],
            ['label' => 'Pedidos', 'path' => '/shop/orders', 'icon' => 'cart', 'permission' => 'list-orders'],
            ['label' => 'Cupones', 'path' => '/shop/coupons', 'icon' => 'tag', 'permission' => 'list-coupons'],
            ['label' => 'Medios', 'path' => '/media', 'icon' => 'photo', 'permission' => 'list-media'],
            ['label' => 'Ajustes', 'path' => '/settings', 'icon' => 'settings', 'permission' => 'manage-settings'],
        ];

        foreach ($items as $position => $item) {
            MenuItem::query()->updateOrCreate(
                ['menu_id' => $admin->id, 'path' => $item['path']],
                [...$item, 'position' => $position * 10],
            );
        }
    }
}
