<?php

namespace App\Services\Shop;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lista de produse a unei categorii / brand / cautari, cu filtrele si sortarile din magazinul vechi
 * (parametrii WooCommerce: orderby, min_price, max_price, filter_{atribut}, query_type). Fatetele
 * (numarul de produse pe brand si pe valoare de atribut) se calculeaza pe acelasi set de baza.
 */
class CatalogQuery
{
    public const SORTS = [
        'menu_order' => 'Recomandate', 'popularity' => 'Cele mai vândute', 'rating' => 'Cele mai apreciate',
        'date' => 'Cele mai noi', 'price' => 'Preț crescător', 'price-desc' => 'Preț descrescător',
    ];

    public const PER_PAGE = [24, 36, 48];

    /** @var list<int>|null */
    private ?array $categoryIds = null;

    private ?int $brandId = null;

    private ?int $tagId = null;

    private ?string $search = null;

    public function __construct(private Request $request) {}

    /** @param list<int> $ids */
    public function inCategories(array $ids): static
    {
        $this->categoryIds = $ids;

        return $this;
    }

    public function forBrand(int $id): static
    {
        $this->brandId = $id;

        return $this;
    }

    public function forTag(int $id): static
    {
        $this->tagId = $id;

        return $this;
    }

    public function matching(?string $term): static
    {
        $this->search = $term !== null && trim($term) !== '' ? mb_substr(trim($term), 0, 100) : null;

        return $this;
    }

    /** Setul de baza: publicat + contextul (categorie, brand, cautare), fara filtrele alese de vizitator. */
    public function base(): Builder
    {
        $q = Product::query()->published();
        if ($this->categoryIds !== null) {
            $q->whereIn('products.id', fn ($s) => $s->select('product_id')->from('category_product')->whereIn('category_id', $this->categoryIds));
        }
        if ($this->brandId) {
            $q->where('brand_id', $this->brandId);
        }
        if ($this->tagId) {
            $q->whereIn('products.id', fn ($s) => $s->select('product_id')->from('product_tag')->where('tag_id', $this->tagId));
        }
        if ($this->search) {
            self::applySearch($q, $this->search);
        }

        return $q;
    }

    /** Cautare pe nume, SKU, brand si cod EAN; fiecare cuvant trebuie sa apara. */
    public static function applySearch(Builder $q, string $term): void
    {
        $words = array_slice(array_filter(preg_split('/\s+/u', $term)), 0, 6);
        foreach ($words as $word) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%';
            $q->where(fn ($w) => $w->where('products.name', 'like', $like)->orWhere('products.sku', 'like', $like)->orWhere('products.gtin', $word)
                ->orWhereIn('products.brand_id', fn ($s) => $s->select('id')->from('brands')->where('name', 'like', $like))
                ->orWhereIn('products.id', fn ($s) => $s->select('product_id')->from('product_variants')->where('sku', 'like', $like)));
        }
    }

    /** Filtrele alese, citite din URL. @return array{brands:list<int>,attrs:array<string,list<string>>,min:?int,max:?int,stock:bool,sale:bool} */
    public function selected(): array
    {
        $attrs = [];
        foreach ($this->request->query() as $key => $value) {
            if (str_starts_with((string) $key, 'filter_') && $key !== 'filter_brand' && is_string($value) && $value !== '') {
                $attrs[substr($key, 7)] = array_values(array_filter(explode(',', $value)));
            }
        }
        $brands = array_values(array_filter(array_map('intval', explode(',', (string) $this->request->query('filter_brand', '')))));

        return [
            'brands' => $brands,
            'attrs' => $attrs,
            'min' => is_numeric($this->request->query('min_price')) ? (int) round($this->request->query('min_price') * 100) : null,
            'max' => is_numeric($this->request->query('max_price')) ? (int) round($this->request->query('max_price') * 100) : null,
            'stock' => $this->request->query('stock_status') === 'instock',
            'sale' => $this->request->boolean('on_sale'),
        ];
    }

    private function applyFilters(Builder $q, array $sel, ?string $skipAttr = null, bool $skipBrand = false): Builder
    {
        if ($sel['brands'] && ! $skipBrand) {
            $q->whereIn('brand_id', $sel['brands']);
        }
        foreach ($sel['attrs'] as $slug => $values) {
            if ($slug === $skipAttr) {
                continue;
            }
            $q->whereIn('products.id', fn ($s) => $s->select('avp.product_id')->from('attribute_value_product as avp')
                ->join('attribute_values as av', 'av.id', '=', 'avp.attribute_value_id')
                ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
                ->where('a.slug', $slug)->whereIn('av.slug', $values));
        }
        if ($sel['min'] !== null) {
            $q->where('price_max', '>=', $sel['min']);
        }
        if ($sel['max'] !== null) {
            $q->where('price', '<=', $sel['max']);
        }
        if ($sel['stock']) {
            $q->inStock();
        }
        if ($sel['sale']) {
            $q->where('on_sale', true);
        }

        return $q;
    }

    public function sort(): string
    {
        $sort = (string) $this->request->query('orderby', $this->search ? 'relevance' : 'menu_order');

        return array_key_exists($sort, self::SORTS) || $sort === 'relevance' ? $sort : 'menu_order';
    }

    public function perPage(): int
    {
        $n = (int) $this->request->query('per_page', 24);

        return in_array($n, self::PER_PAGE, true) ? $n : 24;
    }

    public function paginate(): LengthAwarePaginator
    {
        $q = $this->applyFilters($this->base(), $this->selected())->with(['mainImage', 'brand'])
            ->withCount(['variants as active_variants_count' => fn ($v) => $v->where('is_active', true)]);
        // Produsele epuizate apar mereu la final, ca in magazinul vechi.
        $q->orderByRaw("CASE WHEN stock_status = 'outofstock' THEN 1 ELSE 0 END");
        match ($this->sort()) {
            'popularity' => $q->orderByDesc('sold_count'),
            'rating' => $q->orderByDesc('rating_avg')->orderByDesc('rating_count'),
            'date' => $q->orderByDesc('published_at'),
            'price' => $q->orderBy('price'),
            'price-desc' => $q->orderByDesc('price'),
            'relevance' => $q->orderByRaw('CASE WHEN products.name LIKE ? THEN 0 ELSE 1 END', [str_replace(['%', '_'], ['\%', '\_'], (string) $this->search).'%'])->orderByDesc('sold_count'),
            default => $q->orderByDesc('is_featured')->orderByDesc('sold_count')->orderByDesc('published_at'),
        };

        return $q->orderByDesc('products.id')->paginate($this->perPage())->withQueryString();
    }

    /** Fatetele pentru bara de filtre. */
    public function facets(): array
    {
        $sel = $this->selected();
        $ids = fn (?string $skipAttr = null, bool $skipBrand = false) => $this->applyFilters($this->base(), $sel, $skipAttr, $skipBrand)->select('products.id');

        $brands = Brand::query()->joinSub($this->applyFilters($this->base(), $sel, null, true)->select('brand_id', DB::raw('count(*) as n'))->groupBy('brand_id'), 'c', 'c.brand_id', '=', 'brands.id')
            ->orderBy('brands.name')->get(['brands.id', 'brands.name', 'brands.slug', 'c.n']);

        $attributes = Attribute::query()->where('is_filterable', true)->orderBy('sort')->orderBy('name')->get();
        $attrFacets = [];
        foreach ($attributes as $attribute) {
            $values = DB::table('attribute_values as av')->join('attribute_value_product as avp', 'avp.attribute_value_id', '=', 'av.id')
                ->where('av.attribute_id', $attribute->id)->whereIn('avp.product_id', $ids($attribute->slug))
                ->groupBy('av.id', 'av.name', 'av.slug', 'av.color', 'av.sort')->orderBy('av.sort')->orderBy('av.name')
                ->get(['av.id', 'av.name', 'av.slug', 'av.color', DB::raw('count(*) as n')]);
            if ($values->count() > 1 || ($values->count() === 1 && isset($sel['attrs'][$attribute->slug]))) {
                $attrFacets[] = ['attribute' => $attribute, 'values' => $values];
            }
        }

        $range = $this->base()->selectRaw('min(price) as lo, max(price_max) as hi')->first();

        return [
            'brands' => $brands, 'attributes' => $attrFacets, 'selected' => $sel,
            'price' => ['min' => (int) floor(($range->lo ?? 0) / 100), 'max' => (int) ceil(($range->hi ?? 0) / 100)],
            'sale_count' => $this->applyFilters($this->base(), $sel)->where('on_sale', true)->count(),
        ];
    }

    /** Linkurile „sterge filtrul” pentru filtrele active. @return Collection<int, array{label:string,url:string}> */
    public function activeChips(array $facets): Collection
    {
        $sel = $facets['selected'];
        $chips = collect();
        $without = fn (array $remove, array $set = []) => $this->request->fullUrlWithQuery(array_merge(array_fill_keys($remove, null), $set, ['page' => null]));
        foreach ($facets['brands'] as $brand) {
            if (in_array($brand->id, $sel['brands'], true)) {
                $rest = array_diff($sel['brands'], [$brand->id]);
                $chips->push(['label' => $brand->name, 'url' => $without(['filter_brand'], $rest ? ['filter_brand' => implode(',', $rest)] : [])]);
            }
        }
        foreach ($facets['attributes'] as $f) {
            $slug = $f['attribute']->slug;
            foreach ($f['values'] as $v) {
                if (in_array($v->slug, $sel['attrs'][$slug] ?? [], true)) {
                    $rest = array_diff($sel['attrs'][$slug], [$v->slug]);
                    $chips->push(['label' => $v->name, 'url' => $without(['filter_'.$slug], $rest ? ['filter_'.$slug => implode(',', $rest)] : [])]);
                }
            }
        }
        if ($sel['min'] !== null || $sel['max'] !== null) {
            $chips->push(['label' => 'Preț: '.($sel['min'] !== null ? $sel['min'] / 100 : 0).' – '.($sel['max'] !== null ? $sel['max'] / 100 : '∞').' lei', 'url' => $without(['min_price', 'max_price'])]);
        }
        if ($sel['stock']) {
            $chips->push(['label' => 'Doar în stoc', 'url' => $without(['stock_status'])]);
        }
        if ($sel['sale']) {
            $chips->push(['label' => 'La reducere', 'url' => $without(['on_sale'])]);
        }

        return $chips;
    }
}
