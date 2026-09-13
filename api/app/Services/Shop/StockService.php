<?php

namespace App\Services\Shop;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

class StockService
{
    /** Reserva estándar: 15 minutos (el tiempo típico de un checkout). */
    public const RESERVATION_MINUTES = 15;

    /**
     * Disponible = stock físico − reservas activas. Permite excluir las
     * reservas del propio carrito (si te reservaste 2, tú sigues viendo 2).
     */
    public function available(ProductVariant $variant, ?string $excludeCartUuid = null): int
    {
        if (! $variant->track_stock) {
            return PHP_INT_MAX;
        }

        $reserved = StockReservation::active()
            ->where('variant_id', $variant->getKey())
            ->when($excludeCartUuid, fn ($q) => $q->where('cart_uuid', '!=', $excludeCartUuid))
            ->sum('quantity');

        return max(0, $variant->stock - (int) $reserved);
    }

    /**
     * Crea/renueva las reservas de un carrito. NO impone el límite de stock:
     * el carrito puede contener más unidades de las disponibles y es el
     * checkout (con lock pesimista) quien lo rechaza con 409.
     */
    public function reserve(string $cartUuid, array $lines): void
    {
        DB::transaction(function () use ($cartUuid, $lines) {
            StockReservation::forCart($cartUuid)->delete();

            foreach ($lines as [$variant, $quantity]) {
                $reserved = min($quantity, $this->available($variant->refresh()));

                if ($reserved < 1) {
                    continue;
                }

                StockReservation::create([
                    'variant_id' => $variant->getKey(),
                    'quantity' => $reserved,
                    'cart_uuid' => $cartUuid,
                    'expires_at' => now()->addMinutes((int) config('tcms.stock_reservation_minutes', self::RESERVATION_MINUTES)),
                ]);
            }
        });
    }

    public function releaseCart(string $cartUuid): void
    {
        StockReservation::forCart($cartUuid)->delete();
    }

    /** Confirmación definitiva: decrementa stock (variantes y productos simples). */
    public function commit(string $cartUuid, array $variantLines, array $productLines = []): void
    {
        foreach ($variantLines as [$variant, $quantity]) {
            ProductVariant::query()
                ->whereKey($variant->getKey())
                ->where('track_stock', true)
                ->decrement('stock', $quantity);
        }

        foreach ($productLines as [$product, $quantity]) {
            Product::query()
                ->whereKey($product->getKey())
                ->where('track_stock', true)
                ->decrement('stock', $quantity);
        }

        $this->releaseCart($cartUuid);
    }
}
