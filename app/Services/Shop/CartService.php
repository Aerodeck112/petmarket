<?php

namespace App\Services\Shop;

use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Cosul vizitatorului: un token aleatoriu intr-un cookie de 30 de zile; dupa autentificare cosul se
 * leaga de cont si se uneste cu cel salvat anterior. Cosul contine doar ID-uri si cantitati.
 */
class CartService
{
    public const COOKIE = 'pm_cart';

    private ?Cart $cart = null;

    public function __construct(private PricingEngine $pricing) {}

    public function current(bool $create = false): ?Cart
    {
        if ($this->cart) {
            return $this->cart;
        }
        $user = auth()->user();
        $token = request()->cookie(self::COOKIE);
        $cart = $token ? Cart::query()->where('token', $token)->first() : null;
        if (! $cart && $user) {
            $cart = Cart::query()->where('user_id', $user->id)->latest('updated_at')->first();
        }
        if ($cart && $user && $cart->user_id === null) {
            $cart = $this->adopt($cart, $user->id);
        }
        if (! $cart && $create) {
            $cart = Cart::query()->create(['token' => Str::random(48), 'user_id' => $user?->id]);
        }
        if ($cart && $cart->token !== $token) {
            Cookie::queue(self::COOKIE, $cart->token, 60 * 24 * 30, null, null, null, true, false, 'lax');
        }

        return $this->cart = $cart;
    }

    /** Cosul anonim devine al contului; daca exista deja un cos al contului, produsele se aduna. */
    private function adopt(Cart $cart, int $userId): Cart
    {
        $existing = Cart::query()->where('user_id', $userId)->whereKeyNot($cart->id)->latest('updated_at')->first();
        if ($existing) {
            foreach ($cart->items as $item) {
                $this->increment($existing, $item->product_id, $item->product_variant_id, $item->qty);
            }
            $existing->update(['coupon_code' => $existing->coupon_code ?: $cart->coupon_code]);
            $cart->delete();

            return $existing->fresh();
        }
        $cart->update(['user_id' => $userId]);

        return $cart;
    }

    public function add(Product $product, ?ProductVariant $variant, int $qty): CartItem
    {
        if (! $product->sold_online) {
            throw new InvalidArgumentException('Acest produs nu se comercializează online. Sună-ne pentru detalii.');
        }
        if (! $product->isPurchasable() && ! $variant) {
            throw new InvalidArgumentException('Produsul nu poate fi comandat momentan.');
        }
        if ($product->isVariable() && (! $variant || $variant->product_id !== $product->id || ! $variant->is_active)) {
            throw new InvalidArgumentException('Alege o variantă a produsului.');
        }
        if (! $product->isVariable()) {
            $variant = null;
        }
        $line = new Line($product, $variant, 1);
        if ($line->unitPrice() <= 0) {
            throw new InvalidArgumentException('Produsul nu are preț și nu poate fi comandat.');
        }
        if ($line->available() === 0) {
            throw new InvalidArgumentException('Produsul nu mai este în stoc.');
        }

        $cart = $this->current(true);
        $item = $this->increment($cart, $product->id, $variant?->id, max(1, $qty));
        $cart->touch();

        return $item;
    }

    private function increment(Cart $cart, int $productId, ?int $variantId, int $qty): CartItem
    {
        $item = $cart->items()->where('product_id', $productId)->where('product_variant_id', $variantId)->first()
            ?? new CartItem(['cart_id' => $cart->id, 'product_id' => $productId, 'product_variant_id' => $variantId, 'qty' => 0]);
        $item->qty = min(999, $item->qty + $qty);
        $item->save();

        return $item;
    }

    public function update(int $itemId, int $qty): void
    {
        $cart = $this->current();
        $item = $cart?->items()->whereKey($itemId)->first();
        if (! $item) {
            return;
        }
        $qty <= 0 ? $item->delete() : $item->update(['qty' => min(999, $qty)]);
        $cart->touch();
    }

    public function remove(int $itemId): void
    {
        $this->update($itemId, 0);
    }

    public function setCoupon(?string $code): void
    {
        $this->current(true)->update(['coupon_code' => $code ? mb_strtoupper(trim($code)) : null]);
    }

    public function clear(): void
    {
        if ($cart = $this->current()) {
            $cart->items()->delete();
            $cart->update(['coupon_code' => null]);
        }
    }

    /**
     * Liniile cosului, cu produsele recitite. Produsele sterse, nepublicate sau variantele dezactivate
     * dispar din cos; cantitatile se limiteaza la min/max ale produsului. @return list<Line>
     */
    public function lines(): array
    {
        $cart = $this->current();
        if (! $cart) {
            return [];
        }
        $items = $cart->items()->with(['product.mainImage', 'product.categories', 'variant.values'])->get();
        $lines = [];
        foreach ($items as $item) {
            $product = $item->product;
            if (! $product || $product->status !== 'published' || ! $product->sold_online || ($product->isVariable() && (! $item->variant || ! $item->variant->is_active))) {
                $item->delete();

                continue;
            }
            $qty = max((int) $product->min_qty, $item->qty);
            if ($product->max_qty) {
                $qty = min($qty, (int) $product->max_qty);
            }
            $lines[] = new Line($product, $item->variant, $qty, $item->id);
        }

        return $lines;
    }

    public function quote(?ShippingMethod $method = null, ?PaymentMethod $payment = null, ?string $email = null, ?string $county = null): Quote
    {
        $cart = $this->current();

        return $this->pricing->quote($this->lines(), $cart?->coupon_code, $method, $payment, $email ?? auth()->user()?->email, auth()->id(), $county);
    }

    public function count(): int
    {
        $cart = $this->current();

        return $cart ? (int) $cart->items()->sum('qty') : 0;
    }
}
