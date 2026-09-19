<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * El panel embebido (apps nativas) lo sirve el propio Laravel desde
 * public/ui. En web (Docker) esa carpeta no existe y el comportamiento
 * debe ser el de siempre: welcome en / y 404 en lo demás.
 *
 * Nota: response()->file() no expone el cuerpo al cliente de tests, así que
 * el contenido real se verifica por cabeceras aquí y se verificó por HTTP
 * real (artisan serve + curl) durante la F0.
 */
class SpaControllerTest extends TestCase
{
    private bool $uiExisted = false;

    protected function setUp(): void
    {
        parent::setUp();

        // El fixture se gestiona aquí: si el desarrollador tenía un build en
        // public/ui se aparca y se restaura al terminar.
        $this->uiExisted = is_file(public_path('ui/index.html'));

        if ($this->uiExisted) {
            File::moveDirectory(public_path('ui'), public_path('ui.aparcada'));
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('ui'));

        if ($this->uiExisted) {
            File::moveDirectory(public_path('ui.aparcada'), public_path('ui'));
        }

        parent::tearDown();
    }

    private function crearUi(): void
    {
        File::ensureDirectoryExists(public_path('ui/assets'));
        File::put(
            public_path('ui/index.html'),
            '<!doctype html><html><head><title>panel-test</title></head><body>SPA DE PRUEBA</body></html>',
        );
        File::put(public_path('ui/assets/app-test.js'), 'console.log("test");');
    }

    public function test_sin_ui_embebida_la_raiz_muestra_el_welcome_de_web(): void
    {
        $this->get('/')->assertOk()->assertSee('<!DOCTYPE html>', false);
    }

    public function test_sin_ui_embebida_las_rutas_desconocidas_dan_404(): void
    {
        $this->get('/users')->assertNotFound();
        $this->getJson('/users')->assertNotFound();
    }

    public function test_con_ui_embebida_la_raiz_sirve_el_index_del_panel(): void
    {
        $this->crearUi();

        $response = $this->get('/')->assertOk();

        $this->assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_con_ui_embebida_el_fallback_sirve_las_rutas_del_router(): void
    {
        $this->crearUi();

        // BrowserRouter: cualquier ruta del panel devuelve index.html.
        $this->get('/users')->assertOk();
        $this->get('/settings/media')->assertOk();
    }

    public function test_los_assets_del_build_se_sirven_con_cache_inmutable(): void
    {
        $this->crearUi();

        $response = $this->get('/assets/app-test.js')->assertOk();

        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('immutable', $cache);
        $this->assertStringContainsString('max-age=31536000', $cache);
    }

    public function test_los_prefijos_de_api_e_infraestructura_nunca_son_capturados(): void
    {
        $this->crearUi();

        // Aunque exista UI embebida, el API mantiene su contrato de errores.
        $this->getJson('/api/ruta-inexistente')->assertNotFound();
        $this->getJson('/api/whatever/deep')->assertNotFound();
        $this->get('/sanctum/raro')->assertNotFound();

        // /storage lo sirve el framework (disco con serve); jamás la SPA.
        $this->assertContains(
            $this->get('/storage/secreto')->status(),
            [403, 404],
        );

        // Y sin UI también.
        File::deleteDirectory(public_path('ui'));
        $this->getJson('/api/ruta-inexistente')->assertNotFound();
    }

    public function test_el_fallback_no_deja_escapar_rutas_con_traversal(): void
    {
        $this->crearUi();

        // El traversal se limpia y cae en index.html (html, nunca el .env).
        $this->get('/..%2F..%2F.env')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $this->get('/assets/..%2F..%2F.env')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}
