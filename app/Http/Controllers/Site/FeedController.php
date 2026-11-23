<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Settings;

/** Feed-ul de produse pentru Google Merchant Center (RSS 2.0 cu spatiul de nume g:), generat in flux. */
class FeedController extends Controller
{
    public function google()
    {
        return response()->stream(function () {
            $e = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>';
            echo '<title>'.$e(Settings::get('shop.name')).'</title><link>'.$e(rtrim(config('app.url'), '/').'/').'</link><description>'.$e(Settings::get('shop.tagline')).'</description>';
            Product::query()->published()->where('sold_online', true)->whereNotNull('price')->with(['mainImage', 'brand', 'categories'])
                ->chunkById(500, function ($products) use ($e) {
                    foreach ($products as $p) {
                        $img = $p->mainImage?->url();
                        if (! $img) {
                            continue;
                        }
                        echo '<item>';
                        echo '<g:id>'.$e($p->sku ?: 'PM'.$p->id).'</g:id><g:title>'.$e(mb_substr($p->name, 0, 150)).'</g:title>';
                        echo '<g:description>'.$e(mb_substr(trim(strip_tags((string) ($p->description ?: $p->short_description ?: $p->name))), 0, 4900)).'</g:description>';
                        echo '<g:link>'.$e(site_url($p->path())).'</g:link><g:image_link>'.$e($img).'</g:image_link>';
                        echo '<g:availability>'.match ($p->stock_status->value) { 'instock' => 'in_stock', 'onbackorder' => 'backorder', default => 'out_of_stock' }.'</g:availability>';
                        echo '<g:price>'.number_format(($p->regular_price ?? $p->price) / 100, 2, '.', '').' RON</g:price>';
                        if ($p->on_sale && ! $p->isVariable()) {
                            echo '<g:sale_price>'.number_format($p->price / 100, 2, '.', '').' RON</g:sale_price>';
                        }
                        echo '<g:condition>new</g:condition>';
                        if ($p->brand) {
                            echo '<g:brand>'.$e($p->brand->name).'</g:brand>';
                        }
                        echo $p->gtin ? '<g:gtin>'.$e($p->gtin).'</g:gtin>' : '<g:identifier_exists>no</g:identifier_exists>';
                        if ($cat = $p->primaryCategory()) {
                            echo '<g:product_type>'.$e(str_replace('/', ' > ', $cat->ancestors()->pluck('name')->push($cat->name)->implode('/'))).'</g:product_type>';
                        }
                        if ($p->weight_grams) {
                            echo '<g:shipping_weight>'.$p->weight_grams.' g</g:shipping_weight>';
                        }
                        echo '</item>';
                    }
                });
            echo '</channel></rss>';
        }, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
