<?php

namespace App\Services\Shop;

use App\Models\Product;
use App\Models\ProductVariant;

/** O linie de cos, cu pretul citit mereu din baza de date (niciodata din formular). */
final class Line
{
    public int $discount = 0;

    public function __construct(
        public Product $product,
        public ?ProductVariant $variant,
        public int $qty,
        public ?int $cartItemId = null,
    ) {}

    public function unitPrice(): int
    {
        return (int) ($this->variant ? $this->variant->effectivePrice() : $this->product->effectivePrice());
    }

    public function regularUnitPrice(): int
    {
        return (int) (($this->variant ?? $this->product)->regular_price ?? $this->unitPrice());
    }

    public function onSale(): bool
    {
        return ($this->variant ?? $this->product)->saleIsActive();
    }

    public function subtotal(): int
    {
        return $this->unitPrice() * $this->qty;
    }

    public function total(): int
    {
        return max(0, $this->subtotal() - $this->discount);
    }

    public function weightGrams(): int
    {
        return (int) (($this->variant?->weight_grams ?? $this->product->weight_grams) ?? 0) * $this->qty;
    }

    public function sku(): ?string
    {
        return $this->variant?->sku ?: $this->product->sku;
    }

    public function label(): ?string
    {
        return $this->variant?->displayLabel();
    }

    public function image(): ?string
    {
        return $this->variant?->imageUrl() ?: $this->product->mainImage?->url();
    }

    /** Stocul disponibil pentru aceasta linie; null = nelimitat (stoc negestionat sau precomanda permisa). */
    public function available(): ?int
    {
        $source = $this->variant ?? $this->product;
        if ($this->product->allow_backorders || ! $source->manage_stock || $source->stock_qty === null) {
            return $source->stock_status?->value === 'outofstock' && ! $this->product->allow_backorders ? 0 : null;
        }

        return max(0, (int) $source->stock_qty);
    }

    public function key(): string
    {
        return $this->product->id.':'.($this->variant?->id ?? 0);
    }
}
