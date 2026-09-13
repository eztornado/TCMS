<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seeder_crea_roles_y_catalogo_de_permisos(): void
    {
        $this->seedCore();

        $this->assertTrue(Role::query()->where('name', 'Admin')->exists());
        $this->assertTrue(Role::query()->where('name', 'Editor')->exists());
        $this->assertTrue(Permission::query()->where('name', 'list-users')->exists());
    }

    public function test_login_correcto_crea_sesion(): void
    {
        $this->seedCore();
        $this->seed(AdminUserSeeder::class);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'cambia-esto-ya',
        ]);

        $response->assertOk()->assertJsonPath('user.email', 'admin@example.com');
        $this->assertAuthenticated();
    }

    public function test_login_rechaza_credenciales_incorrectas(): void
    {
        $this->seedCore();
        $this->seed(AdminUserSeeder::class);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'incorrecta',
        ])->assertUnprocessable();
    }

    public function test_usuario_desactivado_no_puede_iniciar_sesion(): void
    {
        $this->seedCore();
        $this->seed(AdminUserSeeder::class);
        User::query()->update(['is_active' => false]);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'cambia-esto-ya',
        ])->assertUnprocessable();
    }

    public function test_me_devuelve_roles_y_permisos_efectivos(): void
    {
        $this->seedCore();
        $user = $this->adminUser();

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles.0.name', 'Admin');

        $this->assertContains('list-users', $response->json('data.permissions'));
    }

    public function test_menu_filtrado_por_permisos_del_admin(): void
    {
        $this->seedCore();
        $user = $this->adminUser();

        $paths = collect($this->actingAs($user)->getJson('/api/auth/menu')->json('data'))->pluck('to');

        $this->assertContains('/users', $paths);
        $this->assertContains('/shop/orders', $paths);
    }

    public function test_menu_oculta_entradas_sin_permiso(): void
    {
        $this->seedCore();
        $user = User::factory()->create();
        $user->assignRole('Editor');

        $paths = collect($this->actingAs($user)->getJson('/api/auth/menu')->json('data'))->pluck('to');

        $this->assertContains('/content', $paths);
        $this->assertContains('/events', $paths);
        $this->assertNotContains('/users', $paths);
        $this->assertNotContains('/shop/orders', $paths);
    }

    public function test_rutas_de_admin_requieren_permiso(): void
    {
        $this->seedCore();

        $this->getJson('/api/admin/users')->assertUnauthorized();

        $user = $this->plainUser();
        $this->actingAs($user)->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_cambio_de_contraseña(): void
    {
        $this->seedCore();
        $user = $this->adminUser(['password' => 'mi-clave-actual']);

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'mal',
            'password' => 'nueva-clave-123',
            'password_confirmation' => 'nueva-clave-123',
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'mi-clave-actual',
            'password' => 'nueva-clave-123',
            'password_confirmation' => 'nueva-clave-123',
        ])->assertOk();
    }
}
