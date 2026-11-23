<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Services\Seo\Seo;
use App\Support\Settings;

/** Sitemap index (ca Yoast / Rank Math): produse impartite pe bucati, categorii si branduri, pagini si articole. */
class SitemapController extends Controller
{
    private const CHUNK = 2000;

    public function sitemap()
    {
        $parts = [];
        $pages = max(1, (int) ceil(Product::query()->published()->count() / self::CHUNK));
        for ($i = 1; $i <= $pages; $i++) {
            $parts[] = site_url('/sitemap-produse-'.$i.'.xml');
        }
        $parts[] = site_url('/sitemap-categorii-1.xml');
        $parts[] = site_url('/sitemap-pagini-1.xml');

        return response()->view('site.sitemap-index', ['parts' => $parts, 'lastmod' => Product::query()->max('updated_at')])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function part(string $type, int $page)
    {
        $urls = [];
        $add = function (string $path, $updated, ?string $image = null) use (&$urls) {
            $urls[] = ['loc' => Seo::canonicalUrl($path), 'lastmod' => $updated?->toDateString(), 'image' => $image];
        };

        if ($type === 'produse') {
            Product::query()->published()->with('mainImage')->orderBy('id')->forPage($page, self::CHUNK)->get(['id', 'slug', 'updated_at'])
                ->each(fn ($p) => $add($p->path(), $p->updated_at, $p->mainImage?->url()));
        } elseif ($type === 'categorii') {
            Category::query()->visible()->orderBy('path')->get()->each(fn ($c) => $add($c->path(), $c->updated_at));
            Brand::query()->orderBy('name')->get()->each(fn ($b) => $add($b->path(), $b->updated_at));
        } else {
            $add('/', null);
            $add('/magazin/', null);
            Page::published()->where('noindex', false)->where('slug', '!=', 'home')->get()->each(fn ($p) => $add($p->path(), $p->updated_at));
            Post::published()->get()->each(fn ($p) => $add($p->path(), $p->updated_at));
        }
        abort_if($urls === [], 404);

        return response()->view('site.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /contul-meu', 'Disallow: /cos', 'Disallow: /finalizare', 'Disallow: /comanda/',
            'Disallow: /cautare', 'Disallow: /*?*filter_', 'Disallow: /*?*orderby=', 'Disallow: /install'];

        if (! app()->isProduction()) {
            $lines = ['User-agent: *', 'Disallow: /'];
        }

        $extra = trim((string) Settings::get('seo.robots_extra'));
        if ($extra !== '') {
            $lines[] = $extra;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml';

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
