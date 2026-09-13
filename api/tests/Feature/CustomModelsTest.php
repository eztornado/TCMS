<?php

namespace Tests\Feature;

use App\Models\CustomModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Motor de contenido dinámico: crear un modelo genera una tabla física real
 * (esquema activo de rotary, pero con validación y whitelists), y el CRUD
 * genérico funciona sobre cualquier entidad sin código adicional.
 */
class CustomModelsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCore();
        $this->admin = $this->adminUser();
    }

    public function test_crear_modelo_con_campos_materializa_tabla_y_permisos(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/models', [
            'slug' => 'testimonios',
            'label' => 'Testimonio',
            'plural_label' => 'Testimonios',
            'has_status' => true,
            'is_taxonomizable' => false,
            'fields' => [
                ['name' => 'autor', 'label' => 'Autor', 'type' => 'text', 'is_required' => true],
                ['name' => 'cuerpo', 'label' => 'Cuerpo', 'type' => 'textarea'],
            ],
        ]);

        $response->assertCreated();
        $this->assertTrue(Schema::hasTable('cm_testimonios'));
        $this->assertTrue(Schema::hasColumn('cm_testimonios', 'autor'));
        $this->assertTrue(Schema::hasColumn('cm_testimonios', 'cuerpo'));
        $this->assertTrue(Schema::hasColumn('cm_testimonios', 'status'));

        // El admin recibe automáticamente los permisos derivados del modelo.
        $this->assertTrue($this->admin->fresh()->can('list-cm-testimonios'));
        $this->assertTrue($this->admin->fresh()->can('create-cm-testimonios'));
    }

    public function test_rechaza_nombres_de_campo_reservados_o_invalidos(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/models', [
                'slug' => 'malicioso',
                'label' => 'Mal',
                'fields' => [
                    ['name' => 'id', 'label' => 'ID', 'type' => 'text'],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_crud_de_entradas_con_validacion_de_requeridos(): void
    {
        $this->crearModelo();

        // Falta el campo requerido → 422 con error de validación.
        $this->actingAs($this->admin)
            ->postJson('/api/cm/testimonios', [
                'cuerpo' => 'Texto del testimonio',
                'status' => 'published',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('autor');

        $created = $this->actingAs($this->admin)
            ->postJson('/api/cm/testimonios', [
                'autor' => 'María',
                'cuerpo' => 'Texto del testimonio',
                'status' => 'published',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('María', $created['autor']);
        $this->assertSame('published', $created['status']);

        // Actualización.
        $updated = $this->actingAs($this->admin)
            ->putJson("/api/cm/testimonios/{$created['id']}", [
                'autor' => 'María G.',
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('María G.', $updated['autor']);

        // Listado con búsqueda sobre campos marcados como buscables.
        $list = $this->actingAs($this->admin)
            ->getJson('/api/cm/testimonios?q=María')
            ->assertOk();

        $this->assertSame(1, $list->json('meta.total'));

        // Borrado.
        $this->actingAs($this->admin)
            ->deleteJson("/api/cm/testimonios/{$created['id']}")
            ->assertOk();

        $this->assertDatabaseMissing('cm_testimonios', ['id' => $created['id']]);
    }

    public function test_el_schema_endpoint_devuelve_el_contrato_de_ui(): void
    {
        $this->crearModelo();

        $schema = $this->actingAs($this->admin)
            ->getJson('/api/cm/testimonios/schema')
            ->assertOk()
            ->json('data');

        $this->assertSame('testimonios', $schema['slug']);
        $this->assertTrue($schema['has_status']);
        $this->assertCount(2, $schema['fields']);
        $this->assertSame('autor', $schema['fields'][0]['name']);
    }

    public function test_borrar_modelo_elimina_tabla(): void
    {
        $this->crearModelo();

        $this->actingAs($this->admin)
            ->deleteJson('/api/admin/models/testimonios')
            ->assertOk();

        $this->assertFalse(Schema::hasTable('cm_testimonios'));
        $this->assertNull(CustomModel::query()->where('slug', 'testimonios')->first());
    }

    private function crearModelo(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/models', [
            'slug' => 'testimonios',
            'label' => 'Testimonio',
            'plural_label' => 'Testimonios',
            'has_status' => true,
            'fields' => [
                ['name' => 'autor', 'label' => 'Autor', 'type' => 'text', 'is_required' => true, 'is_searchable' => true],
                ['name' => 'cuerpo', 'label' => 'Cuerpo', 'type' => 'textarea'],
            ],
        ])->assertCreated();
    }
}
