<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/** Pret obisnuit + pret redus cu interval optional, ca in WooCommerce. Sumele sunt in bani. */
trait HasSalePrice
{
    public function saleIsActive(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->sale_price !== null && $this->regular_price !== null && $this->sale_price < $this->regular_price
            && ($this->sale_starts_at === null || $this->sale_starts_at->lte($at))
            && ($this->sale_ends_at === null || $this->sale_ends_at->gt($at));
    }

    public function effectivePrice(?Carbon $at = null): ?int
    {
        return $this->saleIsActive($at) ? (int) $this->sale_price : ($this->regular_price === null ? null : (int) $this->regular_price);
    }

    /** Procentul reducerii, rotunjit, pentru eticheta „-15%”. */
    public function discountPercent(): ?int
    {
        if (! $this->saleIsActive() || ! $this->regular_price) {
            return null;
        }

        return (int) round(100 - $this->sale_price * 100 / $this->regular_price);
    }
}
