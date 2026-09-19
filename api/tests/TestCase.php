<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Reinicia el contenedor de auth: los guards (p. ej. sanctum, un
     * RequestGuard) memoizan al usuario resuelto mientras la app del test
     * vive, lo que falsearía peticiones con credenciales ya revocadas.
     */
    protected function refreshAuth(): void
    {
        Auth::clearResolvedInstances();
        $this->app->forgetInstance('auth');
        $this->app->forgetInstance('auth.factory');
    }
}
