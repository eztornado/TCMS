<?php

namespace Tests\Unit;

use App\Support\Runtime;
use Tests\TestCase;

class RuntimeTest extends TestCase
{
    protected function tearDown(): void
    {
        config(['tcms.runtime' => 'web']);

        parent::tearDown();
    }

    public function test_por_defecto_es_web(): void
    {
        config(['tcms.runtime' => 'web']);

        $this->assertTrue(Runtime::isWeb());
        $this->assertFalse(Runtime::isNative());
        $this->assertFalse(Runtime::isDesktop());
        $this->assertFalse(Runtime::isMobile());
        $this->assertSame('web', Runtime::context());
    }

    public function test_runtime_desktop(): void
    {
        config(['tcms.runtime' => 'native-desktop']);

        $this->assertTrue(Runtime::isNative());
        $this->assertTrue(Runtime::isDesktop());
        $this->assertFalse(Runtime::isWeb());
        $this->assertFalse(Runtime::isMobile());
        $this->assertSame('native-desktop', Runtime::context());
    }

    public function test_runtime_mobile(): void
    {
        config(['tcms.runtime' => 'native-mobile']);

        $this->assertTrue(Runtime::isNative());
        $this->assertTrue(Runtime::isMobile());
        $this->assertFalse(Runtime::isWeb());
        $this->assertFalse(Runtime::isDesktop());
        $this->assertSame('native-mobile', Runtime::context());
    }

    public function test_contexto_desconocido_se_trata_como_web(): void
    {
        config(['tcms.runtime' => 'algo-raro']);

        $this->assertTrue(Runtime::isWeb());
        $this->assertFalse(Runtime::isNative());
    }

    public function test_sin_config_el_contexto_es_web(): void
    {
        config(['tcms.runtime' => null]);

        $this->assertTrue(Runtime::isWeb());
    }
}
