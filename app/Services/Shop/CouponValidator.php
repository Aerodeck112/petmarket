<?php

namespace App\Services\Shop;

use App\Models\Coupon;

/** Regulile cupoanelor WooCommerce: perioada, limite de folosire, suma minima, restrictii pe produse, categorii, branduri si email. */
class CouponValidator
{
    /** @param list<Line> $lines  @return string|null mesajul de eroare pentru client sau null daca cuponul este valid */
    public function validate(Coupon $coupon, array $lines, int $subtotal, ?string $email, ?int $userId): ?string
    {
        if (! $coupon->is_active) {
            return 'Cuponul nu mai este activ.';
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return 'Cuponul poate fi folosit începând cu '.$coupon->starts_at->format('d.m.Y').'.';
        }
        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return 'Cuponul a expirat.';
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return 'Cuponul a fost deja folosit de numărul maxim de ori.';
        }
        if ($coupon->min_subtotal && $subtotal < $coupon->min_subtotal) {
            return 'Cuponul se aplică pentru comenzi de minimum '.money($coupon->min_subtotal).'.';
        }
        if ($coupon->max_subtotal && $subtotal > $coupon->max_subtotal) {
            return 'Cuponul se aplică pentru comenzi de maximum '.money($coupon->max_subtotal).'.';
        }
        $allowed = array_map('mb_strtolower', array_filter((array) $coupon->allowed_emails));
        if ($allowed !== [] && $email !== null && ! $this->emailAllowed(mb_strtolower($email), $allowed)) {
            return 'Cuponul nu este valabil pentru adresa de email folosită.';
        }
        if ($coupon->usage_limit_per_user !== null && ($email || $userId)) {
            $used = $coupon->usages()->where(fn ($q) => $q->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when($email, fn ($q) => $q->orWhere('email', mb_strtolower($email))))->count();
            if ($used >= $coupon->usage_limit_per_user) {
                return 'Ai folosit deja acest cupon.';
            }
        }
        if ($lines !== [] && ! collect($lines)->contains(fn (Line $l) => $this->appliesTo($coupon, $l))) {
            return 'Cuponul nu se aplică produselor din coș.';
        }

        return null;
    }

    public function appliesTo(Coupon $coupon, Line $line): bool
    {
        $product = $line->product;
        if ($coupon->exclude_sale_items && $line->onSale()) {
            return false;
        }
        if (in_array($product->id, (array) $coupon->excluded_product_ids)) {
            return false;
        }
        $categoryIds = $product->relationLoaded('categories') ? $product->categories->pluck('id')->all() : $product->categories()->pluck('categories.id')->all();
        if (array_intersect($categoryIds, (array) $coupon->excluded_category_ids)) {
            return false;
        }
        $restricted = ! empty($coupon->product_ids) || ! empty($coupon->category_ids) || ! empty($coupon->brand_ids);
        if (! $restricted) {
            return true;
        }

        return in_array($product->id, (array) $coupon->product_ids)
            || array_intersect($categoryIds, (array) $coupon->category_ids)
            || ($product->brand_id && in_array($product->brand_id, (array) $coupon->brand_ids));
    }

    /** Accepta si adrese cu caracter global, ca in WooCommerce: *@firma.ro. */
    private function emailAllowed(string $email, array $allowed): bool
    {
        foreach ($allowed as $pattern) {
            if ($pattern === $email || (str_contains($pattern, '*') && fnmatch($pattern, $email))) {
                return true;
            }
        }

        return false;
    }
}
