<?php

namespace Tests\Unit;

use App\Models\CustomModel;
use App\Support\Sync\SyncRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El registry es el contrato del sync: si alguien añade una tabla mal
 * definida (modelo inexistente, refs a tablas no syncables, orden
 * incompleto), esto lo detecta antes de que el protocolo se rompa.
 */
class SyncRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_toda_tabla_tiene_modelo_existente_y_politica_valida(): void
    {
        foreach (SyncRegistry::tables() as $table => $definition) {
            $this->assertArrayHasKey('model', $definition, "[$table] sin modelo");
            $this->assertTrue(
                class_exists($definition['model']),
                "[$table] el modelo {$definition['model']} no existe.",
            );
            $this->assertContains(
                $definition['policy'],
                ['pull', 'bidi'],
                "[$table] política desconocida: {$definition['policy']}",
            );
            $this->assertIsArray($definition['refs'], "[$table] refs no es un array");
        }
    }

    public function test_todas_las_refs_apuntan_a_tablas_del_propio_registry_o_dinamicas(): void
    {
        $tables = array_keys(SyncRegistry::tables());

        foreach (SyncRegistry::tables() as $table => $definition) {
            foreach ($definition['refs'] as $column => $related) {
                $this->assertContains(
                    $related,
                    array_merge($tables, SyncRegistry::dynamicTables()),
                    "[$table.$column] apunta a [$related] que no sincroniza.",
                );
            }
        }
    }

    public function test_los_pivots_referencian_tablas_syncables(): void
    {
        $tables = array_keys(SyncRegistry::tables());

        foreach (SyncRegistry::pivots() as $pivot => $definition) {
            foreach ($definition['refs'] as $column => $related) {
                $this->assertContains($related, $tables, "[$pivot.$column] apunta a [$related] fuera del registry.");
            }
        }
    }

    public function test_apply_order_cubre_todas_las_tablas_estaticas(): void
    {
        $this->assertSame(
            [],
            array_diff(array_keys(SyncRegistry::tables()), SyncRegistry::applyOrder()),
            'Hay tablas del registry fuera del orden de aplicación.',
        );
    }

    public function test_policy_de_tabla_dinamica_es_bidi_y_desconocida_es_null(): void
    {
        CustomModel::create([
            'slug' => 'libros',
            'label' => 'Libro',
            'plural_label' => 'Libros',
            'table_name' => 'cm_libros',
        ]);

        $this->assertSame('bidi', SyncRegistry::policyOf('cm_libros'));
        $this->assertSame('pull', SyncRegistry::policyOf('users'));
        $this->assertNull(SyncRegistry::policyOf('orders'));
        $this->assertNull(SyncRegistry::policyOf('no_existe'));
    }

    public function test_issyncable_distingue_estaticas_dinamicas_y_ajenas(): void
    {
        $this->assertTrue(SyncRegistry::isSyncable('products'));
        $this->assertFalse(SyncRegistry::isSyncable('orders'));
        $this->assertFalse(SyncRegistry::isSyncable('sessions'));
    }
}
