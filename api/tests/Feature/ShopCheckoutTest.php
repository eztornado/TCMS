<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\StockReservation;
use App\Services\Shop\CartService;
use App\Services\Shop\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function createProduct(array $attributes = []): Product
    {
        return Product::factory()->create([
            'status' => 'active',
            'price_cents' => 2500,
            'stock' => 10,
            'track_stock' => true,
            ...$attributes,
        ]);
    }

    protected function addToCart(Product $product, int $quantity, ?int $variantId = null): string
    {
        return $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ])->assertOk()->json('cart_uuid');
    }

    public function test_el_catalogo_publico_solo_muestra_activos(): void
    {
        $this->createProduct(['title' => 'Visible']);
        $this->createProduct(['title' => 'Borrador', 'status' => 'draft']);

        $titles = collect($this->getJson('/api/store/products')->assertOk()->json('data'))
            ->pluck('title');

        $this->assertContains('Visible', $titles);
        $this->assertNotContains('Borrador', $titles);
    }

    public function test_checkout_completo_decrementa_stock_y_crea_pedido(): void
    {
        $this->seedCore();
        $product = $this->createProduct();
        $cartUuid = $this->addToCart($product, 3);

        $response = $this->postJson('/api/store/checkout', [
            'cart_uuid' => $cartUuid,
            'email' => 'comprador@test.com',
            'customer_name' => 'Comprador',
            'success_url' => 'https://shop.test/ok',
            'cancel_url' => 'https://shop.test/ko',
        ]);

        $response->assertCreated();
        $order = Order::query()->where('number', $response->json('data.number'))->firstOrFail();

        // Numeración legible: TC-2026-000001
        $this->assertMatchesRegularExpression('/^TC-\d{4}-\d{6}$/', $order->number);
        $this->assertSame(7500, $order->subtotal_cents);
        $this->assertSame(7500, $order->total_cents);
        $this->assertCount(1, $order->items);

        // Snapshot de la línea: sobrevive a cambios y borrados del catálogo.
        $this->assertSame($product->title, $order->items->first()->title);
        $this->assertSame(2500, $order->items->first()->unit_price_cents);

        // Stock descontado definitivamente.
        $this->assertSame(7, $product->fresh()->stock);

        // El carrito queda vacío y sin reservas.
        $this->assertSame(0, StockReservation::count());
    }

    public function test_checkout_con_variantes_reserva_stock(): void
    {
        $this->seedCore();
        $product = $this->createProduct(['stock' => 0]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'TCM-M',
            'name' => 'Talla M',
            'price_cents' => 2900,
            'stock' => 2,
            'is_default' => true,
        ]);

        $cartUuid = $this->addToCart($product, 2, $variant->id);

        // Con la reserva activa, el disponible real es 0.
        $this->assertSame(0, app(StockService::class)->available($variant));

        $response = $this->postJson('/api/store/checkout', [
            'cart_uuid' => $cartUuid,
            'email' => 'v@test.com',
            'customer_name' => 'V',
            'success_url' => 'https://shop.test/ok',
            'cancel_url' => 'https://shop.test/ko',
        ])->assertCreated();

        $this->assertSame(5800, $response->json('data.subtotal_cents'));
        $this->assertSame(0, $variant->fresh()->stock);
    }

    public function test_no_se_puede_comprar_mas_stock_del_disponible(): void
    {
        $product = $this->createProduct(['stock' => 1]);

        // El carrito acepta más unidades del stock real: es el checkout
        // (bajo lock) quien lo rechaza con 409.
        $cartUuid = $this->addToCart($product, 5);

        $this->postJson('/api/store/checkout', [
            'cart_uuid' => $cartUuid,
            'email' => 'comprador@test.com',
            'customer_name' => 'Comprador',
            'success_url' => 'https://shop.test/ok',
            'cancel_url' => 'https://shop.test/ko',
        ])->assertStatus(409);

        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_carrito_vacio_no_permite_checkout(): void
    {
        $cart = Cart::create();

        $this->postJson('/api/store/checkout', [
            'cart_uuid' => $cart->uuid,
            'email' => 'x@test.com',
            'customer_name' => 'X',
            'success_url' => 'https://shop.test/ok',
            'cancel_url' => 'https://shop.test/ko',
        ])->assertUnprocessable();
    }

    public function test_cupon_valido_aplica_descuento_en_servidor(): void
    {
        $this->seedCore();
        Coupon::create([
            'code' => 'LANZAMIENTO',
            'type' => 'percentage',
            'percentage' => 20,
            'is_active' => true,
        ]);
        $product = $this->createProduct();

        $cartUuid = $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->json('cart_uuid');

        $quote = $this->postJson('/api/store/cart/coupon', [
            'cart_uuid' => $cartUuid,
            'code' => 'lanzamiento', // case-insensitive
        ])->assertOk()->json('data');

        $this->assertSame(5000, $quote['subtotal_cents']);
        $this->assertSame(1000, $quote['discount_cents']);
        $this->assertSame(4000, $quote['total_cents']);
    }

    public function test_cupon_caducado_o_inexistente_rechazado(): void
    {
        Coupon::factory()->create([
            'code' => 'VIEJO',
            'type' => 'fixed',
            'amount_cents' => 500,
            'ends_at' => now()->subDay(),
        ]);

        $this->postJson('/api/store/cart/coupon', [
            'cart_uuid' => Cart::create()->uuid,
            'code' => 'VIEJO',
        ])->assertUnprocessable();

        $this->postJson('/api/store/cart/coupon', [
            'cart_uuid' => Cart::create()->uuid,
            'code' => 'NOEXISTE',
        ])->assertUnprocessable();
    }

    public function test_gastos_de_envio_aplicados_desde_el_servidor(): void
    {
        $this->seedCore();
        ShippingMethod::create([
            'name' => 'Estándar',
            'type' => 'flat',
            'cost_cents' => 599,
            'free_over_cents' => 5000,
            'is_active' => true,
        ]);
        $product = $this->createProduct(['price_cents' => 2000]);

        $cartUuid = $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->json('cart_uuid');

        // Bajo el umbral de envío gratis: se cobra 5,99 €.
        $quote = $this->postJson('/api/store/cart/shipping', [
            'cart_uuid' => $cartUuid,
            'shipping_method_id' => 1,
        ])->assertOk()->json('data');

        $this->assertSame(599, $quote['shipping_cents']);
        $this->assertSame(2000 + 599, $quote['total_cents']);
    }

    public function test_maquina_de_estados_impide_transiciones_invalidas(): void
    {
        $this->seedCore();
        $order = Order::create([
            'number' => 'TC-2026-999999',
            'email' => 'a@b.c',
            'customer_name' => 'A',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_cents' => 100,
            'total_cents' => 100,
            'placed_at' => now(),
        ]);

        $this->actingAs($this->adminUser())
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'pending'])
            ->assertUnprocessable();

        // completed → refunded sí es válida.
        $this->actingAs($this->adminUser())
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'refunded'])
            ->assertOk();
    }

    public function test_consulta_publica_de_pedido_por_numero_y_email(): void
    {
        Order::create([
            'number' => 'TC-2026-555555',
            'email' => 'dueño@test.com',
            'customer_name' => 'D',
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal_cents' => 100,
            'total_cents' => 100,
            'placed_at' => now(),
        ]);

        $this->getJson('/api/store/orders/TC-2026-555555?email=dueño@test.com')
            ->assertOk()
            ->assertJsonPath('data.number', 'TC-2026-555555');

        // Email incorrecto: no se filtra el pedido.
        $this->getJson('/api/store/orders/TC-2026-555555?email=ladrón@test.com')
            ->assertNotFound();
    }

    public function test_el_quote_es_la_unica_fuente_de_precio(): void
    {
        $product = $this->createProduct(['price_cents' => 1000]);
        $cart = Cart::create();
        $service = app(CartService::class);
        $service->addItem($cart, $product->id, null, 3);

        $quote = $service->quote($cart);

        $this->assertSame(3000, $quote['subtotal_cents']);
        $this->assertSame(3000, $quote['total_cents']);
        $this->assertSame('EUR', $quote['currency']);
    }
}
