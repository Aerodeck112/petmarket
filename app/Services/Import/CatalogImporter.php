<?php

namespace App\Services\Import;

use App\Enums\StockStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Redirect;
use App\Models\Tag;
use App\Support\Html;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Importul catalogului din exportul magazinului WooCommerce (database/seeders/data/catalog, extras din
 * Store API-ul public). Este repetabil: fiecare entitate se potriveste dupa ID-ul WooCommerce, deci o
 * rulare noua actualizeaza preturile si stocurile fara duplicate. Produsele se importa pe loturi, ca o
 * cerere din browser pe cPanel sa nu depaseasca limita de timp.
 */
class CatalogImporter
{
    /** Categoria implicita WooCommerce nu se afiseaza: produsele din ea raman fara categorie. */
    public const HIDDEN_CATEGORIES = ['fara-categorie', 'uncategorized'];

    public function __construct(private ?string $dir = null)
    {
        $this->dir ??= database_path('seeders/data/catalog');
    }

    public function available(): bool
    {
        return is_file($this->dir.'/categories.json') && glob($this->dir.'/products-*.json');
    }

    /** @return list<array> */
    private function json(string $file): array
    {
        $path = $this->dir.'/'.$file;
        if (! is_file($path)) {
            throw new RuntimeException('Lipsește fișierul '.$file.' din '.$this->dir);
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<array> toate produsele exportate, in ordinea ID-ului */
    public function products(): array
    {
        return Cache::store('array')->rememberForever('import.products', function () {
            $all = [];
            foreach (glob($this->dir.'/products-*.json') ?: [] as $file) {
                array_push($all, ...$this->json(basename($file)));
            }
            usort($all, fn ($a, $b) => $a['id'] <=> $b['id']);

            return $all;
        });
    }

    public function totalProducts(): int
    {
        return count($this->products());
    }

    /** Pasul 1: categorii, branduri, etichete, pagini, redirecturi. Rapid; se poate rula oricand. */
    public function importTaxonomies(): array
    {
        return DB::transaction(function () {
            $stats = ['categorii' => 0, 'branduri' => 0, 'etichete' => 0, 'pagini' => 0];

            // Categorii: intai toate, apoi parintii (exportul nu e ordonat dupa ierarhie).
            $rows = collect($this->json('categories.json'));
            $map = [];
            foreach ($rows as $row) {
                $cat = Category::query()->firstOrNew(['wc_term_id' => $row['id']]);
                $cat->fill([
                    'name' => self::text($row['name']), 'slug' => $row['slug'], 'description' => $row['description'] ?: null,
                    'is_visible' => ! in_array($row['slug'], self::HIDDEN_CATEGORIES, true), 'in_menu' => ! in_array($row['slug'], self::HIDDEN_CATEGORIES, true),
                    'parent_id' => null, 'path' => $row['slug'],
                ]);
                $cat->saveQuietly();
                $map[$row['id']] = $cat;
                $stats['categorii']++;
            }
            foreach ($rows as $row) {
                if ($row['parent'] && isset($map[$row['parent']])) {
                    $map[$row['id']]->parent_id = $map[$row['parent']]->id;
                    $map[$row['id']]->saveQuietly();
                }
            }
            // Caile se recalculeaza de la radacina in jos, nivel cu nivel.
            $level = Category::query()->whereNull('parent_id')->get();
            while ($level->isNotEmpty()) {
                $level->each(fn (Category $c) => $c->saveQuietly() || true);
                $level->each(function (Category $c) {
                    $c->children()->get()->each(function (Category $child) use ($c) {
                        $child->forceFill(['path' => $c->path.'/'.$child->slug, 'depth' => $c->depth + 1])->saveQuietly();
                    });
                });
                $level = Category::query()->whereIn('parent_id', $level->pluck('id'))->get();
            }
            $this->applyMenuOrder($map);

            // Branduri: taxonomia product_brand + valorile atributului local „Brand”.
            $brands = $this->json('brands.json');
            foreach ($brands['wp_product_brand'] ?? $brands['store_brands'] ?? [] as $row) {
                Brand::query()->updateOrCreate(['wc_term_id' => $row['id']], ['name' => self::text($row['name']), 'slug' => $row['slug'], 'description' => $row['description'] ?: null]);
                $stats['branduri']++;
            }
            foreach ($brands['_from_local_attribute_Brand'] ?? [] as $row) {
                if (! $this->brandByName($row['name'])) {
                    Brand::query()->create(['name' => self::text($row['name']), 'slug' => $this->uniqueBrandSlug($row['name'])]);
                    $stats['branduri']++;
                }
            }

            foreach ($this->json('tags.json') as $row) {
                Tag::query()->updateOrCreate(['wc_term_id' => $row['id']], ['name' => self::text($row['name']), 'slug' => $row['slug']]);
                $stats['etichete']++;
            }

            $stats['pagini'] = $this->importPages();
            $this->legacyRedirects();
            Cache::forget('catalog.tree');

            return $stats;
        });
    }

    /** Ordinea categoriilor din meniul principal al magazinului vechi (menus.json), daca exista. */
    private function applyMenuOrder(array $map): void
    {
        $menuFile = dirname($this->dir).'/pages/menus.json';
        if (! is_file($menuFile)) {
            return;
        }
        $menu = json_decode((string) file_get_contents($menuFile), true)['main'] ?? [];
        $walk = function (array $items, int $depth) use (&$walk) {
            foreach ($items as $i => $item) {
                if (preg_match('#/categorie-produs/(.+?)/?$#', (string) ($item['url'] ?? ''), $m)) {
                    // Eticheta din meniul vechi (ex. „Suplimente” pentru categoria „OTC”) devine numele afisat la primul nivel.
                    Category::query()->where('path', trim($m[1], '/'))->update(['sort' => $i + 1, 'is_featured' => $depth === 0]
                        + ($depth === 0 && ! empty($item['title']) ? ['name' => self::text($item['title'])] : []));
                }
                if (! empty($item['children'])) {
                    $walk($item['children'], $depth + 1);
                }
            }
        };
        $walk($menu, 0);
        // In meniul de sus raman doar categoriile din meniul vechi; restul sunt accesibile din magazin si cautare.
        $top = collect($menu)->map(fn ($i) => preg_match('#/categorie-produs/([^/]+)/?$#', (string) ($i['url'] ?? ''), $m) ? $m[1] : null)->filter()->all();
        if ($top) {
            Category::query()->whereNull('parent_id')->whereNotIn('slug', $top)->update(['in_menu' => false]);
        }
    }

    /** Pasul 2: un lot de produse. Returneaza cate au fost procesate (0 = gata). */
    public function importProducts(int $offset, int $limit): int
    {
        $batch = array_slice($this->products(), $offset, $limit);
        if ($batch === []) {
            return 0;
        }
        $categories = Category::query()->whereNotNull('wc_term_id')->pluck('id', 'wc_term_id');
        $hidden = Category::query()->whereIn('slug', self::HIDDEN_CATEGORIES)->pluck('id')->all();
        $depths = Category::query()->pluck('depth', 'id');
        $tags = Tag::query()->whereNotNull('wc_term_id')->pluck('id', 'wc_term_id');
        $brandsByTerm = Brand::query()->whereNotNull('wc_term_id')->pluck('id', 'wc_term_id');

        DB::transaction(function () use ($batch, $categories, $hidden, $depths, $tags, $brandsByTerm) {
            foreach ($batch as $row) {
                $this->importProduct($row, $categories, $hidden, $depths, $tags, $brandsByTerm);
            }
        });

        return count($batch);
    }

    private function importProduct(array $row, $categories, array $hidden, $depths, $tags, $brandsByTerm): void
    {
        $product = Product::withTrashed()->firstOrNew(['wc_product_id' => $row['id']]);
        $prices = $row['prices'] ?? [];
        $regular = self::minor($prices['regular_price'] ?? null);
        $sale = self::minor($prices['sale_price'] ?? null);
        $onSale = ! empty($row['on_sale']) && $sale !== null && $regular !== null && $sale < $regular;

        // Stocul: WooCommerce publica doar textul „N în stoc” (cand stocul e gestionat) sau nimic.
        $text = (string) ($row['stock_availability']['text'] ?? '');
        $qty = preg_match('/(\d+)\s+în stoc/u', $text, $m) ? (int) $m[1] : null;
        $backorder = ! empty($row['is_on_backorder']);
        $status = $backorder ? StockStatus::OnBackorder : (! empty($row['is_in_stock']) ? StockStatus::InStock : StockStatus::OutOfStock);

        $sku = trim((string) ($row['sku'] ?? '')) ?: null;
        if ($sku && Product::withTrashed()->where('sku', $sku)->where('wc_product_id', '!=', $row['id'])->exists()) {
            $sku = null; // SKU duplicat in sursa: ramane doar pe primul produs.
        }

        $brandId = null;
        foreach ($row['brands'] ?? [] as $b) {
            $brandId ??= $brandsByTerm[$b['id']] ?? null;
        }
        if (! $brandId) {
            foreach ($row['attributes'] ?? [] as $attr) {
                if (mb_strtolower((string) $attr['name']) === 'brand' && ! empty($attr['terms'][0]['name'])) {
                    $brandId = $this->brandByName($attr['terms'][0]['name'])?->id;
                }
            }
        }

        $weight = is_numeric($row['weight'] ?? null) ? (int) round((float) $row['weight'] * 1000) : null;
        $product->fill([
            'type' => 'simple', 'status' => 'published', 'name' => self::text($row['name']), 'slug' => $row['slug'], 'sku' => $sku,
            'brand_id' => $brandId, 'short_description' => self::html($row['short_description'] ?? ''), 'description' => self::html($row['description'] ?? ''),
            'regular_price' => $regular, 'sale_price' => $onSale ? $sale : null, 'manage_stock' => $qty !== null, 'stock_qty' => $qty,
            'stock_status' => $status, 'allow_backorders' => $backorder, 'weight_grams' => $weight ?: null,
            'sold_online' => (bool) ($row['is_purchasable'] ?? true), 'is_visible' => true,
            'max_qty' => ! empty($row['sold_individually']) ? 1 : null,
        ]);
        if ($qty === null) {
            // Fara stoc gestionat, statusul vine direct din sursa (hook-ul modelului nu il recalculeaza).
            $product->stock_status = $status;
        }
        $product->deleted_at = null;
        $product->save();

        $catIds = collect($row['categories'] ?? [])->map(fn ($c) => $categories[$c['id']] ?? null)->filter()->reject(fn ($id) => in_array($id, $hidden, true))->unique()->values();
        $primary = $catIds->sortByDesc(fn ($id) => $depths[$id] ?? 0)->first();
        $product->categories()->sync($catIds->mapWithKeys(fn ($id) => [$id => ['is_primary' => $id === $primary]])->all());
        $product->tags()->sync(collect($row['tags'] ?? [])->map(fn ($t) => $tags[$t['id']] ?? null)->filter()->unique()->values()->all());

        // Imaginile: se pastreaza cele deja descarcate; cele noi se adauga cu adresa sursa.
        $existing = $product->images()->get()->keyBy('source_url');
        $keep = [];
        foreach (array_values($row['images'] ?? []) as $i => $img) {
            $src = $img['src'] ?? null;
            if (! $src) {
                continue;
            }
            $image = $existing[$src] ?? new ProductImage(['product_id' => $product->id, 'source_url' => $src]);
            $image->fill(['alt' => self::text($img['alt'] ?: $row['name']), 'sort' => $i])->save();
            $keep[] = $image->id;
        }
        $product->images()->whereNotIn('id', $keep)->delete();
    }

    /** Paginile legale si „Despre noi” din magazinul vechi (fara cele generate de WooCommerce / Elementor). */
    private function importPages(): int
    {
        $file = dirname($this->dir).'/pages/pages.json';
        if (! is_file($file)) {
            return 0;
        }
        $wanted = [
            'contact' => ['contact', 'contact'], 'livrare' => ['livrare', 'legal'], 'termeni-conditii' => ['termeni-conditii', 'legal'],
            'refund_returns' => ['politica-de-retur', 'legal'], 'politica-confidentialitate' => ['politica-confidentialitate', 'legal'], 'about-us' => ['despre-noi', 'default'],
        ];
        $n = 0;
        foreach (json_decode((string) file_get_contents($file), true) as $row) {
            if (! isset($wanted[$row['slug']])) {
                continue;
            }
            [$slug, $template] = $wanted[$row['slug']];
            $page = Page::query()->firstOrNew(['slug' => $slug]);
            if ($page->exists && $page->wp_post_id === null && ! str_contains((string) $page->body, 'se preia din magazinul actual')) {
                continue; // pagina editata deja in noua platforma: nu se suprascrie
            }
            $title = self::text($row['title']['rendered'] ?? $row['title'] ?? $slug);
            $body = self::html(self::stripBuilder((string) ($row['content']['rendered'] ?? $row['content'] ?? '')));
            $page->fill(['title' => $title === 'Privacy Policy' ? 'Politica de confidențialitate' : $title, 'template' => $template, 'status' => 'published',
                'published_at' => $page->published_at ?? now(), 'body' => $template === 'contact' ? null : $body, 'wp_post_id' => $row['id']])->save();
            $n++;
        }

        return $n;
    }

    /** Adresele vechi care nu mai exista ca atare: 301 catre echivalentul nou. */
    private function legacyRedirects(): void
    {
        $map = [
            '/refund_returns/' => '/politica-de-retur/', '/about-us/' => '/despre-noi/', '/best-deals/' => '/magazin/?on_sale=1',
            '/brands/' => '/branduri/', '/wishlist/' => '/contul-meu/favorite/', '/compare/' => '/magazin/', '/sample-page/' => '/',
            '/home-pet/' => '/', '/shop/' => '/magazin/', '/checkout/' => '/finalizare/', '/cart/' => '/cos/', '/categorie-produs/fara-categorie/' => '/magazin/',
            '/contul-meu/lost-password/' => '/contul-meu/parola-pierduta/', '/contul-meu/orders/' => '/contul-meu/comenzi/',
            '/contul-meu/edit-address/' => '/contul-meu/adrese/', '/contul-meu/edit-account/' => '/contul-meu/detalii/',
        ];
        foreach ($map as $from => $to) {
            Redirect::query()->firstOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => 301, 'note' => 'Adresă din magazinul WooCommerce']);
        }
        Cache::forget('redirects.map');
    }

    private function brandByName(string $name): ?Brand
    {
        $name = self::text($name);

        return Brand::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->orWhere('slug', Str::slug($name))->first();
    }

    private function uniqueBrandSlug(string $name): string
    {
        $base = Str::slug(self::text($name)) ?: 'brand';
        $slug = $base;
        $i = 2;
        while (Brand::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** Suma din Store API (string in unitati minore) → bani. */
    public static function minor(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    public static function text(?string $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public static function html(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Html::clean($value);
    }

    /** Elimina shortcode-urile si marcajele constructorilor de pagini, pastrand textul. */
    private static function stripBuilder(string $html): string
    {
        $html = preg_replace('/\[\/?[a-z_]+[^\]]*\]/i', '', $html) ?? $html;

        return preg_replace('/<(div|section|span)[^>]*>|<\/(div|section|span)>/i', '', $html) ?? $html;
    }
}
