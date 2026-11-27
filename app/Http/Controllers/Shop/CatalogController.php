<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Services\Seo\Seo;
use App\Services\Shop\CatalogQuery;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function shop(Request $request, Seo $seo)
    {
        $seo->page('Magazin', 'Toate produsele Pet Market: hrană, recompense, accesorii, igienă și sănătate pentru câini și pisici.')
            ->crumbs([['Acasă', '/'], ['Magazin', '/magazin/']]);

        return $this->listing($request, $seo, new CatalogQuery($request), 'Magazin', null, [
            'categories' => Category::tree(),
        ]);
    }

    public function category(Request $request, Seo $seo, string $path)
    {
        $path = trim($path, '/');
        // Paginarea WooCommerce (/categorie-produs/x/page/2/) devine ?page=2, cu redirect permanent.
        if (preg_match('#^(.+)/page/(\d+)$#', $path, $m)) {
            return redirect()->away(site_url('/categorie-produs/'.$m[1].'/').($m[2] > 1 ? '?page='.$m[2] : ''), 301);
        }
        $category = Category::query()->visible()->where('path', $path)->first();
        if (! $category) {
            // Adresa veche doar cu slug-ul ultimei categorii: redirect permanent la calea completa.
            $slug = basename($path);
            $category = Category::query()->visible()->where('slug', $slug)->firstOrFail();

            return redirect()->away(site_url($category->path()), 301);
        }

        $crumbs = [['Acasă', '/'], ['Magazin', '/magazin/']];
        foreach ($category->ancestors() as $a) {
            $crumbs[] = [$a->name, $a->path()];
        }
        $crumbs[] = [$category->name, $category->path()];
        // Subcategoriile cu nume generic („Uscată”, „Câini”) primesc în titlu și părinții, ca fiecare pagină să aibă titlu unic.
        $trail = collect($category->ancestors())->pluck('name')->reverse()->take(2)->prepend($category->name)->implode(' – ');
        $count = $category->product_count;
        $seo->page($category->seo_title ?: $trail, $category->seo_description ?: ($category->description
            ?: $trail.': '.$count.' '.($count === 1 ? 'produs' : 'produse').' la Pet Market by HiKeVet. Prețuri afișate cu TVA, livrare în toată țara și retur în 14 zile.'), $category->image)->crumbs($crumbs);

        $query = (new CatalogQuery($request))->inCategories($category->descendantIds());

        return $this->listing($request, $seo, $query, $category->name, $category, [
            'subcategories' => $category->children()->visible()->get(),
            'banner' => Banner::query()->live('category')->where('category_id', $category->id)->first(),
        ]);
    }

    public function brand(Request $request, Seo $seo, string $slug)
    {
        $brand = Brand::query()->where('slug', $slug)->firstOrFail();
        $seo->page($brand->seo_title ?: $brand->name, $brand->seo_description ?: ($brand->description ?: 'Produse '.$brand->name.' la Pet Market.'), $brand->logo)
            ->crumbs([['Acasă', '/'], ['Branduri', '/branduri/'], [$brand->name, $brand->path()]]);
        // Brand fără produse publicate: pagina rămâne accesibilă, dar nu se indexează.
        $seo->noindex = $seo->noindex || ! $brand->products()->published()->exists();

        return $this->listing($request, $seo, (new CatalogQuery($request))->forBrand($brand->id), $brand->name, null, ['brand' => $brand]);
    }

    public function brands(Seo $seo)
    {
        $seo->page('Branduri', 'Toate mărcile din magazinul Pet Market.')->crumbs([['Acasă', '/'], ['Branduri', '/branduri/']]);
        $brands = Brand::query()->withCount(['products' => fn ($q) => $q->published()])->orderBy('name')->get()->where('products_count', '>', 0);

        return view('shop.brands', ['seo' => $seo, 'brands' => $brands->groupBy(fn ($b) => mb_strtoupper(mb_substr($b->name, 0, 1)))]);
    }

    public function tag(Request $request, Seo $seo, string $slug)
    {
        $tag = Tag::query()->where('slug', $slug)->firstOrFail();
        $seo->page($tag->name)->crumbs([['Acasă', '/'], ['Magazin', '/magazin/'], [$tag->name, '/eticheta-produs/'.$tag->slug.'/']]);

        return $this->listing($request, $seo, (new CatalogQuery($request))->forTag($tag->id), $tag->name);
    }

    public function search(Request $request, Seo $seo)
    {
        $term = trim((string) ($request->query('q', $request->query('s', ''))));
        $seo->page($term !== '' ? 'Rezultate pentru „'.$term.'”' : 'Căutare')->crumbs([['Acasă', '/'], ['Căutare', '/cautare/']]);
        $seo->noindex = true;

        // Un singur rezultat cu SKU identic duce direct la produs.
        if ($term !== '' && ($exact = Product::query()->published()->where('sku', $term)->first())) {
            return redirect()->away(site_url($exact->path()));
        }

        return $this->listing($request, $seo, (new CatalogQuery($request))->matching($term), $term !== '' ? 'Rezultate pentru „'.$term.'”' : 'Căutare', null, ['term' => $term]);
    }

    /** Sugestii pentru caseta de cautare din antet (JSON). */
    public function suggest(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['products' => [], 'categories' => []]);
        }
        $q = Product::query()->published()->with('mainImage');
        CatalogQuery::applySearch($q, $term);
        $products = $q->orderByRaw("CASE WHEN stock_status = 'outofstock' THEN 1 ELSE 0 END")->orderByDesc('sold_count')->limit(6)->get()
            ->map(fn (Product $p) => ['name' => $p->name, 'url' => site_url($p->path()), 'image' => $p->mainImage?->thumbUrl(),
                'price' => $p->price !== null ? ($p->isVariable() && $p->price_max > $p->price ? 'de la ' : '').money($p->price) : null,
                'regular' => $p->on_sale && ! $p->isVariable() ? money($p->regular_price) : null, 'stock' => $p->stock_status->value]);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $categories = Category::query()->visible()->where('name', 'like', $like)->orderBy('depth')->limit(4)->get()
            ->map(fn ($c) => ['name' => $c->name, 'url' => site_url($c->path())]);

        return response()->json(['products' => $products, 'categories' => $categories, 'all' => site_url('/cautare/?q='.rawurlencode($term))]);
    }

    private function listing(Request $request, Seo $seo, CatalogQuery $query, string $title, ?Category $category = null, array $extra = [])
    {
        $products = $query->paginate();
        if ($products->currentPage() > 1) {
            $seo->canonical = rtrim($seo->canonical, '/').'/?page='.$products->currentPage();
        }
        // Paginile filtrate nu se indexeaza (evita mii de URL-uri duplicate).
        if (collect($request->query())->keys()->contains(fn ($k) => str_starts_with($k, 'filter_') || in_array($k, ['min_price', 'max_price', 'orderby', 'stock_status', 'on_sale', 'per_page'], true))) {
            $seo->noindex = true;
        }
        $facets = $query->facets();

        if ($request->boolean('partial')) {
            return view('shop.partials.listing', compact('products', 'facets', 'query') + ['chips' => $query->activeChips($facets)]);
        }

        return view('shop.listing', array_merge([
            'seo' => $seo, 'title' => $title, 'category' => $category, 'products' => $products, 'facets' => $facets,
            'query' => $query, 'chips' => $query->activeChips($facets),
        ], $extra));
    }
}
