<?php

namespace App\Services\Shop;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;

/** Orice modificare de stoc trece pe aici si lasa o miscare in registrul de stoc. */
class StockService
{
    public function adjust(Product $product, ?ProductVariant $variant, int $change, string $reason, ?Order $order = null, ?string $note = null): void
    {
        $source = $variant ?? $product;
        if (! $source->manage_stock || $change === 0) {
            return;
        }
        $source->stock_qty = (int) $source->stock_qty + $change;
        $source->save();
        if ($variant) {
            $product->syncFromVariants();
        }

        StockMovement::query()->create([
            'product_id' => $product->id, 'product_variant_id' => $variant?->id, 'change' => $change, 'stock_after' => $source->stock_qty,
            'reason' => $reason, 'order_id' => $order?->id, 'user_id' => auth()->id(), 'note' => $note,
        ]);
    }

    /** Stocul absolut, din admin (inventar): se inregistreaza diferenta. */
    public function set(Product $product, ?ProductVariant $variant, int $qty, ?string $note = null): void
    {
        $source = $variant ?? $product;
        $source->manage_stock = true;
        $this->adjust($product, $variant, $qty - (int) $source->stock_qty, 'manual', null, $note);
    }
}
