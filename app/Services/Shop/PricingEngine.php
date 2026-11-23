<?php

namespace App\Services\Shop;

use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\ShippingMethod;
use App\Support\Settings;

/**
 * Calculul cosului, facut numai pe server: preturi curente, cupon, livrare, taxa de ramburs si TVA.
 * Preturile sunt cu TVA inclus (ca in magazinul vechi); TVA-ul se extrage din total pe fiecare linie.
 */
class PricingEngine
{
    public function __construct(private CouponValidator $coupons) {}

    /** @param list<Line> $lines */
    public function quote(array $lines, ?string $couponCode = null, ?ShippingMethod $method = null, ?PaymentMethod $payment = null,
        ?string $email = null, ?int $userId = null, ?string $county = null): Quote
    {
        $q = new Quote($lines);
        foreach ($lines as $line) {
            $q->subtotal += $line->subtotal();
            $q->savings += max(0, $line->regularUnitPrice() - $line->unitPrice()) * $line->qty;
            $available = $line->available();
            if ($available !== null && $line->qty > $available) {
                $q->problems[] = $available === 0
                    ? '„'.$line->product->name.'” nu mai este în stoc.'
                    : 'Pentru „'.$line->product->name.'” mai sunt doar '.$available.' bucăți în stoc.';
            }
        }

        if ($couponCode) {
            $coupon = Coupon::query()->where('code', mb_strtoupper(trim($couponCode)))->first();
            $error = $coupon ? $this->coupons->validate($coupon, $lines, $q->subtotal, $email, $userId) : 'Cuponul „'.e($couponCode).'” nu există.';
            if ($error) {
                $q->couponError = $error;
            } else {
                $q->coupon = $coupon;
                $this->applyCoupon($coupon, $lines);
                $q->discount = array_sum(array_map(fn (Line $l) => $l->discount, $lines));
            }
        }

        $afterDiscount = $q->subtotal - $q->discount;
        $threshold = (int) Settings::get('commerce.free_shipping_threshold');

        if ($method) {
            $q->shippingMethod = $method;
            $methodThreshold = $method->free_over === null ? $threshold : (int) $method->free_over;
            $q->freeShipping = ($q->coupon?->free_shipping) || ($methodThreshold > 0 && $afterDiscount >= $methodThreshold);
            if (! $q->freeShipping) {
                $extraKg = max(0, (int) ceil(($q->weightGrams() - 1000) / 1000));
                $q->shipping = (int) $method->cost + $extraKg * (int) $method->cost_per_kg;
            }
            if ($method->counties && $county && ! in_array($county, $method->counties, true)) {
                $q->problems[] = 'Metoda de livrare „'.$method->name.'” nu este disponibilă în județul ales.';
            }
            if ($method->max_weight_grams && $q->weightGrams() > $method->max_weight_grams) {
                $q->problems[] = 'Coletul depășește greutatea maximă pentru „'.$method->name.'”.';
            }
        }
        if ($threshold > 0 && ! $q->coupon?->free_shipping) {
            $q->freeShippingRemaining = max(0, $threshold - $afterDiscount);
        }

        if ($payment === PaymentMethod::Cod) {
            $q->fee = (int) Settings::get('payments.cod_fee');
            if ($method && ! $method->cod_allowed) {
                $q->problems[] = 'Plata la livrare nu este disponibilă pentru „'.$method->name.'”.';
            }
        }

        $q->total = $afterDiscount + $q->shipping + $q->fee;
        $standard = (float) Settings::get('commerce.vat_rate');
        foreach ($lines as $line) {
            $q->vat += self::vatFromGross($line->total(), $line->product->vatRate());
        }
        $q->vat += self::vatFromGross($q->shipping + $q->fee, $standard);

        $min = (int) Settings::get('commerce.min_order');
        if ($min > 0 && $q->subtotal < $min && $lines !== []) {
            $q->problems[] = 'Valoarea minimă a unei comenzi este '.money($min).'.';
        }

        return $q;
    }

    /** TVA continut intr-o suma cu TVA inclus, rotunjit la ban. */
    public static function vatFromGross(int $gross, float $rate): int
    {
        return $rate <= 0 ? 0 : (int) round($gross - $gross / (1 + $rate / 100));
    }

    /** Imparte reducerea pe liniile eligibile; restul de la rotunjire merge pe ultima linie. @param list<Line> $lines */
    private function applyCoupon(Coupon $coupon, array $lines): void
    {
        $eligible = array_values(array_filter($lines, fn (Line $l) => $this->coupons->appliesTo($coupon, $l)));
        if ($eligible === []) {
            return;
        }

        if ($coupon->type === 'percent') {
            foreach ($eligible as $line) {
                $line->discount = (int) round($line->subtotal() * (float) $coupon->percent / 100);
            }

            return;
        }

        if ($coupon->type === 'fixed_product') {
            foreach ($eligible as $line) {
                $line->discount = min($line->subtotal(), (int) $coupon->amount * $line->qty);
            }

            return;
        }

        // fixed_cart: suma se imparte proportional cu valoarea liniilor eligibile.
        $base = array_sum(array_map(fn (Line $l) => $l->subtotal(), $eligible));
        $amount = min((int) $coupon->amount, $base);
        $given = 0;
        foreach ($eligible as $i => $line) {
            $share = $i === array_key_last($eligible) ? $amount - $given : (int) floor($amount * $line->subtotal() / max(1, $base));
            $line->discount = min($line->subtotal(), $share);
            $given += $line->discount;
        }
    }
}
