<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Services\Seo\Seo;
use App\Support\Settings;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request, Seo $seo, string $slug)
    {
        $product = Product::query()->where('slug', $slug)
            ->with(['images', 'brand', 'categories', 'attributes', 'attributeValues.attribute', 'variants' => fn ($q) => $q->where('is_active', true)->with('values.attribute')])
            ->firstOrFail();
        $preview = $product->status !== 'published' && $request->user()?->can('products.manage');
        abort_unless($product->status === 'published' || $preview, 404);

        $category = $product->primaryCategory();
        $crumbs = [['Acasă', '/'], ['Magazin', '/magazin/']];
        if ($category) {
            foreach ($category->ancestors() as $a) {
                $crumbs[] = [$a->name, $a->path()];
            }
            $crumbs[] = [$category->name, $category->path()];
        }
        $crumbs[] = [$product->name, $product->path()];
        $seo->page($product->seo_title ?: $product->name, $product->seo_description ?: ($product->short_description ?: $product->description), $product->images->first()?->url())
            ->crumbs($crumbs);
        $seo->type = 'product';
        $seo->noindex = $preview;
        $seo->schema($this->schema($product));

        $variants = $product->variants->map(fn ($v) => [
            'id' => $v->id, 'label' => $v->displayLabel(), 'sku' => $v->sku, 'price' => $v->effectivePrice(), 'regular' => $v->regular_price,
            'onSale' => $v->saleIsActive(), 'lowest30' => $v->saleIsActive() ? $product->lowestPrice30($v->id) : null,
            'stock' => $v->stock_status->value, 'qty' => $v->manage_stock ? $v->stock_qty : null, 'image' => $v->imageUrl(),
            'values' => $v->values->mapWithKeys(fn ($val) => [$val->attribute->slug => $val->slug])->all(),
        ])->values();
        $variationAttributes = $product->attributes->where('pivot.for_variations', true)->map(function ($attribute) use ($product) {
            $used = $product->variants->flatMap->values->where('attribute_id', $attribute->id)->unique('id')->sortBy([['sort', 'asc'], ['name', 'asc']]);

            return ['slug' => $attribute->slug, 'name' => $attribute->name, 'type' => $attribute->type,
                'values' => $used->map(fn ($v) => ['slug' => $v->slug, 'name' => $v->name, 'color' => $v->color])->values()];
        })->filter(fn ($a) => count($a['values']) > 0)->values();

        $specs = $product->attributes->where('pivot.is_visible', true)->map(fn ($a) => [
            'name' => $a->name, 'value' => $product->attributeValues->where('attribute_id', $a->id)->pluck('name')->implode(', '),
        ])->filter(fn ($s) => $s['value'] !== '')->values();

        $related = Product::query()->published()->inStock()->with('mainImage')->whereKeyNot($product->id)
            ->whereIn('id', fn ($q) => $q->select('product_id')->from('category_product')->where('category_id', $category?->id ?? 0))
            ->orderByDesc('sold_count')->limit(8)->get();
        $upsells = $product->upsells()->published()->with('mainImage')->limit(8)->get();

        $reviews = $product->reviews()->where('status', 'approved')->latest()->limit(30)->get();
        $canReview = Settings::get('commerce.reviews_enabled') && auth()->check();

        return view('shop.product', compact('seo', 'product', 'variants', 'variationAttributes', 'specs', 'related', 'upsells', 'reviews', 'canReview', 'category', 'preview')
            + ['lowest30' => ! $product->isVariable() && $product->on_sale ? $product->lowestPrice30() : null]);
    }

    public function review(Request $request, string $slug)
    {
        abort_unless(Settings::get('commerce.reviews_enabled'), 404);
        $product = Product::query()->published()->where('slug', $slug)->firstOrFail();
        $data = $request->validate(['rating' => 'required|integer|between:1,5', 'body' => 'required|string|min:10|max:3000']);
        $user = $request->user();
        abort_unless($user, 403);

        $verified = OrderItem::query()->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->whereIn('status', ['completed', 'shipped']))->exists();
        Review::query()->create([
            'product_id' => $product->id, 'user_id' => $user->id, 'author_name' => $user->first_name ?: $user->name, 'author_email' => $user->email,
            'rating' => $data['rating'], 'body' => strip_tags($data['body']), 'verified_owner' => $verified,
            'status' => Settings::get('commerce.reviews_need_approval') ? 'pending' : 'approved',
        ]);

        return redirect()->away(site_url($product->path()).'#recenzii')->with('review_sent', true);
    }

    private function schema(Product $product): array
    {
        $offers = $product->isVariable() && $product->price_max > $product->price
            ? ['@type' => 'AggregateOffer', 'lowPrice' => number_format($product->price / 100, 2, '.', ''), 'highPrice' => number_format($product->price_max / 100, 2, '.', ''),
                'offerCount' => $product->variants->count(), 'priceCurrency' => 'RON']
            : ['@type' => 'Offer', 'price' => number_format(($product->price ?? 0) / 100, 2, '.', ''), 'priceCurrency' => 'RON', 'url' => Seo::canonicalUrl($product->path())];
        $offers['availability'] = 'https://schema.org/'.match ($product->stock_status->value) { 'instock' => 'InStock', 'onbackorder' => 'BackOrder', default => 'OutOfStock' };

        $schema = [
            '@type' => 'Product', 'name' => $product->name, 'sku' => $product->sku, 'gtin13' => $product->gtin,
            'image' => $product->images->take(5)->map->url()->filter()->values()->all(),
            'description' => mb_substr(trim(strip_tags((string) ($product->short_description ?: $product->description))), 0, 500),
            'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
            'offers' => $offers,
        ];
        if ($product->rating_count > 0) {
            $schema['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $product->rating_avg, 'reviewCount' => $product->rating_count];
        }

        return $schema;
    }
}
