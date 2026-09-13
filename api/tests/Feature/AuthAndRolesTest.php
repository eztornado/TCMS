<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Autenticación SPA (cookies de sesión), exposición de permisos y menú
 * filtrado en backend (idea clave de reigreengroup, ahora con Spatie real).
 */
class AuthAndRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_con_credenciales_validas_inicia_sesion(): void
    {
        $this->seedCore();
        $user = parent::adminUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('user.email', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rechaza_usuarios_desactivados(): void
    {
        $this->seedCore();
        $user = parent::adminUser();
        $user->update(['is_active' => false]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_me_devuelve_roles_y_permisos_resueltos(): void
    {
        $this->seedCore();
        $user = parent::adminUser();
        $this->actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'email', 'roles', 'permissions'],
            ]);
    }

    public function test_menu_sin_sesion_devuelve_401(): void
    {
        $this->seedCore();
        $this->getJson('/api/auth/menu')->assertUnauthorized();
    }

    public function test_usuario_sin_permiso_recibe_403_en_ruta_protegida(): void
    {
        $this->seedCore();

        $editor = $this->plainUser();
        $editor->assignRole('Editor'); // los editores no gestionan usuarios

        $this->actingAs($editor)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_admin_accede_a_rutas_de_gestion(): void
    {
        $this->seedCore();
        $admin = $this->adminUser();

        $this->actingAs($admin)->getJson('/api/admin/users')->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/roles')->assertOk();
    }

    public function test_crear_y_editar_rol_sincroniza_permisos(): void
    {
        $this->seedCore();
        $admin = $this->adminUser();

        $created = $this->actingAs($admin)->postJson('/api/admin/roles', [
            'name' => 'Gestor de eventos',
            'label' => 'Eventos',
            'permissions' => ['list-events', 'create-events', 'list-bookings'],
        ])->assertCreated()->assertJsonPath('data.name', 'Gestor de eventos');

        $roleId = $created->json('data.id');

        // El `name` no es editable (identificador estable); los permisos sí.
        $this->actingAs($admin)->putJson("/api/admin/roles/{$roleId}", [
            'label' => 'Eventos',
            'permissions' => ['list-events', 'edit-events'],
        ])->assertOk();

        $role = Role::findById($roleId);
        $this->assertEqualsCanonicalizing(['list-events', 'edit-events'], $role->permissions->pluck('name')->all());
    }

    public function test_logout_cierra_la_sesion(): void
    {
        $this->seedCore();
        $user = parent::adminUser();
        $this->actingAs($user);

        $this->postJson('/api/auth/logout')->assertOk();
        $this->assertGuest();
    }
}
