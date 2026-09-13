<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Usuario con rol Admin listo para operar como administrador. */
    protected function adminUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('Admin');

        return $user;
    }

    /** Usuario sin ningún rol ni permiso. */
    protected function plainUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /** Siembra el core (roles, permisos, menú, ajustes) una sola vez por test. */
    protected function seedCore(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(SettingSeeder::class);
    }
}
