<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Seo\Seo;
use App\Services\Shop\CartService;
use App\Services\Shop\Line;
use App\Services\Shop\Quote;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function show(Seo $seo)
    {
        $seo->page('Coș de cumpărături')->crumbs([['Acasă', '/'], ['Coș', '/cos/']]);
        $seo->noindex = true;
        $quote = $this->cart->quote();
        $suggestions = $quote->isEmpty() ? collect() : Product::query()->published()->inStock()->with('mainImage')
            ->whereIn('id', fn ($q) => $q->select('linked_id')->from('product_links')->where('type', 'crosssell')->whereIn('product_id', array_map(fn (Line $l) => $l->product->id, $quote->lines)))
            ->whereNotIn('id', array_map(fn (Line $l) => $l->product->id, $quote->lines))->limit(4)->get();

        return view('shop.cart', ['seo' => $seo, 'quote' => $quote, 'suggestions' => $suggestions]);
    }

    public function add(Request $request)
    {
        $data = $request->validate(['product_id' => 'required|integer', 'variant_id' => 'nullable|integer', 'qty' => 'nullable|integer|min:1|max:999']);
        $product = Product::query()->published()->findOrFail($data['product_id']);
        $variant = ! empty($data['variant_id']) ? ProductVariant::query()->where('product_id', $product->id)->find($data['variant_id']) : null;

        try {
            $this->cart->add($product, $variant, (int) ($data['qty'] ?? 1));
        } catch (InvalidArgumentException $e) {
            return $this->respond($request, $e->getMessage(), false);
        }

        return $this->respond($request, '„'.$product->name.'” a fost adăugat în coș.');
    }

    public function update(Request $request)
    {
        foreach ((array) $request->input('qty', []) as $itemId => $qty) {
            $this->cart->update((int) $itemId, (int) $qty);
        }

        return $this->respond($request, 'Coșul a fost actualizat.');
    }

    public function remove(Request $request, int $item)
    {
        $this->cart->remove($item);

        return $this->respond($request, 'Produsul a fost scos din coș.');
    }

    public function coupon(Request $request)
    {
        if ($request->boolean('remove')) {
            $this->cart->setCoupon(null);

            return $this->respond($request, 'Cuponul a fost eliminat.');
        }
        $code = trim((string) $request->validate(['code' => 'required|string|max:60'])['code']);
        $this->cart->setCoupon($code);
        $quote = $this->cart->quote();
        if ($quote->couponError) {
            $this->cart->setCoupon(null);

            return $this->respond($request, $quote->couponError, false);
        }

        return $this->respond($request, 'Cuponul '.mb_strtoupper($code).' a fost aplicat.');
    }

    /** Raspuns pentru formularele clasice (redirect) si pentru coșul lateral (JSON). */
    private function respond(Request $request, string $message, bool $ok = true)
    {
        if ($request->expectsJson()) {
            $quote = $this->cart->quote();

            return response()->json(['ok' => $ok, 'message' => $message, 'count' => $quote->itemsCount(), 'mini' => view('shop.partials.mini-cart', ['quote' => $quote])->render()], $ok ? 200 : 422);
        }

        return redirect()->back(fallback: site_url('/cos/'))->with($ok ? 'cart_ok' : 'cart_error', $message);
    }

    public static function summary(Quote $quote): array
    {
        return ['count' => $quote->itemsCount(), 'total' => money($quote->total)];
    }
}
