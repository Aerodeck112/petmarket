<?php

namespace App\Services\Shop;

use App\Models\Coupon;
use App\Models\ShippingMethod;

/** Rezultatul calculului unui cos: totaluri, cupon, livrare, TVA, mesaje pentru client. */
final class Quote
{
    /** @param list<Line> $lines */
    public function __construct(
        public array $lines,
        public int $subtotal = 0,
        public int $discount = 0,
        public int $shipping = 0,
        public int $fee = 0,
        public int $vat = 0,
        public int $total = 0,
        public ?Coupon $coupon = null,
        public ?string $couponError = null,
        public ?ShippingMethod $shippingMethod = null,
        public bool $freeShipping = false,
        public ?int $freeShippingRemaining = null,
        public int $savings = 0,
        /** @var list<string> */
        public array $problems = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function itemsCount(): int
    {
        return array_sum(array_map(fn (Line $l) => $l->qty, $this->lines));
    }

    public function weightGrams(): int
    {
        return array_sum(array_map(fn (Line $l) => $l->weightGrams(), $this->lines));
    }
}
