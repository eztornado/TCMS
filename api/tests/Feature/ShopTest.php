<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ecommerce: carrito server-side, precio calculado SIEMPRE en servidor,
 * checkout transaccional con stock, cupones reales y máquina de estados
 * de pedido con historial.
 */
class ShopTest extends TestCase
{
    use RefreshDatabase;

    private function createVariantProduct(int $stock = 10, int $priceCents = 2000): Product
    {
        $product = Product::create([
            'slug' => 'entrada-digital',
            'title' => 'Entrada digital',
            'status' => 'active',
            'track_stock' => true,
        ]);

        $product->variants()->create([
            'sku' => 'ENT-1',
            'name' => 'Estándar',
            'price_cents' => $priceCents,
            'stock' => $stock,
            'track_stock' => true,
            'is_default' => true,
        ]);

        return $product->fresh();
    }

    public function test_carrito_calcula_totales_en_servidor(): void
    {
        $product = $this->createVariantProduct();
        $variant = $product->variants->first();

        $added = $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk();

        $cartUuid = $added->json('cart.uuid') ?? $added->json('uuid') ?? $added->json('data.cart.uuid');
        $this->assertNotNull($cartUuid, 'El carrito devuelto debe incluir su uuid.');

        $cart = $this->getJson('/api/store/cart?cart_uuid='.$cartUuid)->assertOk();

        // 2 × 20,00 € = 40,00 € de subtotal, en céntimos.
        $this->assertSame(4000, $cart->json('quote.subtotal_cents') ?? $cart->json('subtotal_cents'));
    }

    public function test_checkout_crea_pedido_con_snapshot_y_descuenta_stock(): void
    {
        $product = $this->createVariantProduct(stock: 5);
        $variant = $product->variants->first();

        $added = $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 3,
        ])->assertOk();

        $cartUuid = $added->json('cart.uuid') ?? $added->json('uuid') ?? $added->json('data.cart.uuid');

        $checkout = $this->postJson('/api/store/checkout', [
            'cart_uuid' => $cartUuid,
            'email' => 'comprador@example.com',
            'customer_name' => 'Comprador',
            'success_url' => 'https://tienda.test/gracias',
            'cancel_url' => 'https://tienda.test/carrito',
        ])->assertCreated();

        $order = $checkout->json('data');
        $this->assertMatchesRegularExpression('/^TC-\d{4}-\d{6}$/', $order['number']);
        $this->assertSame('comprador@example.com', $order['email']);

        $line = $order['items'][0];
        // Snapshot de línea: el título y el precio quedan congelados en el pedido.
        $this->assertSame('Entrada digital', $line['title']);
        $this->assertSame('ENT-1', $line['sku']);
        $this->assertSame(3, $line['quantity']);

        $this->assertSame(2, $variant->fresh()->stock, 'El stock debe decrementarse al confirmar el pedido.');
    }

    public function test_no_se_puede_comprar_mas_stock_del_disponible(): void
    {
        $product = $this->createVariantProduct(stock: 1);
        $variant = $product->variants->first();

        $added = $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 5,
        ]);

        // O se rechaza al añadir (stock insuficiente) o se rechaza en checkout.
        if ($added->status() === 201 || $added->status() === 200) {
            $cartUuid = $added->json('cart.uuid') ?? $added->json('uuid') ?? $added->json('data.cart.uuid');
            $this->postJson('/api/store/checkout', [
                'cart_uuid' => $cartUuid,
                'email' => 'comprador@example.com',
                'customer_name' => 'Comprador',
                'success_url' => 'https://tienda.test/gracias',
                'cancel_url' => 'https://tienda.test/carrito',
            ])->assertStatus(409);
        } else {
            $added->assertStatus(422);
        }

        $this->assertSame(1, $variant->fresh()->stock);
    }

    public function test_cupon_de_porcentaje_aplica_descuento(): void
    {
        Coupon::create([
            'code' => 'VERANO25',
            'type' => 'percentage',
            'percentage' => 25,
            'is_active' => true,
        ]);

        $product = $this->createVariantProduct();
        $variant = $product->variants->first();

        $added = $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $cartUuid = $added->json('cart.uuid') ?? $added->json('uuid') ?? $added->json('data.cart.uuid');

        $this->postJson('/api/store/cart/coupon', [
            'cart_uuid' => $cartUuid,
            'code' => 'VERANO25',
        ])->assertOk();

        $cart = $this->getJson('/api/store/cart?cart_uuid='.$cartUuid)->assertOk();

        $subtotal = $cart->json('quote.subtotal_cents') ?? $cart->json('subtotal_cents');
        $discount = $cart->json('quote.discount_cents') ?? $cart->json('discount_cents');

        $this->assertSame(2000, $subtotal);
        $this->assertSame(500, $discount);
    }

    public function test_cupon_agotado_no_se_aplica(): void
    {
        Coupon::create([
            'code' => 'AGOTADO',
            'type' => 'fixed',
            'amount_cents' => 500,
            'usage_limit' => 1,
            'usage_count' => 1,
            'is_active' => true,
        ]);

        $product = $this->createVariantProduct();
        $variant = $product->variants->first();

        $added = $this->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
        ])->assertOk();
        $cartUuid = $added->json('cart.uuid') ?? $added->json('uuid') ?? $added->json('data.cart.uuid');

        $this->postJson('/api/store/cart/coupon', [
            'cart_uuid' => $cartUuid,
            'code' => 'AGOTADO',
        ])->assertStatus(422);
    }

    public function test_transicion_de_estado_invalida_se_rechaza_con_historial(): void
    {
        $this->seedCore();
        $admin = $this->adminUser();
        $product = $this->createVariantProduct();
        $variant = $product->variants->first();

        $added = $this->actingAs($admin)->postJson('/api/store/cart/items', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
        ])->assertOk();
        $cartUuid = $added->json('cart.uuid') ?? $added->json('uuid') ?? $added->json('data.cart.uuid');

        $order = $this->postJson('/api/store/checkout', [
            'cart_uuid' => $cartUuid,
            'email' => 'comprador@example.com',
            'customer_name' => 'Comprador',
            'success_url' => 'https://tienda.test/gracias',
            'cancel_url' => 'https://tienda.test/carrito',
        ])->assertCreated()->json('data');

        // completed no es alcanzable desde pending.
        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order['id']}/status", ['status' => 'completed'])
            ->assertStatus(422);

        // pending → processing sí es válida y queda en el historial.
        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order['id']}/status", ['status' => 'processing', 'note' => 'Preparando'])
            ->assertOk();

        $fresh = Order::find($order['id']);
        $this->assertSame('processing', $fresh->status->value);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $fresh->id,
            'to_status' => 'processing',
        ]);
    }

    public function test_admin_puede_listar_pedidos(): void
    {
        $this->seedCore();
        $admin = $this->adminUser();

        Order::create([
            'number' => 'TC-2026-000001',
            'email' => 'a@example.com',
            'customer_name' => 'A',
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal_cents' => 1000,
            'total_cents' => 1000,
            'currency' => 'EUR',
            'placed_at' => now(),
        ]);

        $this->actingAs($admin)->getJson('/api/admin/orders')->assertOk();
    }
}
