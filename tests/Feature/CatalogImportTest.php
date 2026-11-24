<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Redirect;
use App\Services\Import\CatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_repeatable_and_keeps_woocommerce_rules(): void
    {
        $this->seedShop();
        $importer = new CatalogImporter(base_path('tests/Fixtures/catalog'));
        $importer->importTaxonomies();
        $total = $importer->totalProducts();
        $this->assertSame($total, $importer->importProducts(0, 100));
        $importer->refreshCounts();

        $this->assertSame($total, Product::query()->count());
        $this->assertGreaterThan(0, Brand::query()->count());
        $this->assertFalse(Category::query()->where('slug', 'fara-categorie')->value('is_visible'));
        // Calea categoriei urmeaza ierarhia din WooCommerce.
        $deep = Category::query()->where('depth', '>', 0)->first();
        $this->assertStringContainsString('/', $deep->path);
        $source = collect($importer->products());
        $this->assertSame($source->where('is_purchasable', false)->count(), Product::query()->where('sold_online', false)->count());
        $this->assertSame($source->where('is_on_backorder', true)->count(), Product::query()->where('stock_status', 'onbackorder')->count());
        $this->assertSame($source->where('on_sale', true)->count(), Product::query()->where('on_sale', true)->count());
        $this->assertGreaterThan(0, Product::query()->whereNotNull('brand_id')->count());
        $this->assertTrue(Page::query()->where('slug', 'livrare')->whereNotNull('wp_post_id')->exists());
        $this->assertTrue(Redirect::query()->where('from_path', '/refund_returns/')->exists());

        // A doua rulare actualizeaza, nu dubleaza.
        $importer->importProducts(0, 100);
        $this->assertSame($total, Product::query()->count());

        $product = Product::query()->whereNotNull('brand_id')->first();
        $this->get($product->path())->assertOk()->assertSee($product->name);
        $this->get($deep->path())->assertOk();
        // Adresa veche doar cu slug-ul ultimei categorii duce la calea completa.
        $this->get('/categorie-produs/'.$deep->slug)->assertRedirect(site_url($deep->path()));
    }

    public function test_images_are_copied_from_local_wordpress_uploads_as_webp(): void
    {
        $this->seedShop();
        $dir = sys_get_temp_dir().'/pm-wp-uploads-'.uniqid();
        mkdir($dir.'/2026/02', 0777, true);
        $gd = imagecreatetruecolor(1600, 1200);
        imagejpeg($gd, $dir.'/2026/02/poza.jpg');
        config(['petmarket.legacy_uploads_path' => $dir]);
        $product = $this->product();
        $image = $product->images()->create(['source_url' => 'https://pet-market.ro/wp-content/uploads/2026/02/poza.jpg']);

        [$ok, $failed, $blocked] = app(\App\Services\Import\ImageDownloader::class)->run(10);

        $this->assertSame([1, 0, false], [$ok, $failed, $blocked]);
        $image->refresh();
        $this->assertSame(1000, $image->width);
        $this->assertFileExists(public_path('uploads/'.$image->path));
        $this->assertStringEndsWith('-400.webp', $image->thumbUrl());
        @unlink(public_path('uploads/'.$image->path));
        @unlink(public_path('uploads/'.preg_replace('/\.webp$/', '-400.webp', $image->path)));
    }

    public function test_new_taxonomy_species_brands_and_old_category_redirects(): void
    {
        $this->seedShop();
        $importer = new CatalogImporter(base_path('tests/Fixtures/catalog-v2'));
        $stats = $importer->importTaxonomies();
        $importer->importProducts(0, 100);
        $importer->refreshCounts();

        $this->assertSame(['Câini', 'Pisici', 'Alte animale', 'Cabinet veterinar'], Category::query()->whereNull('parent_id')->orderBy('sort')->pluck('name')->all());
        $this->assertTrue(Category::query()->where('path', 'caini/hrana-uscata')->exists());
        $this->assertTrue(Category::query()->where('path', 'pisici/hrana-uscata')->exists());
        $this->assertSame(0, Product::query()->doesntHave('categories')->count());
        $this->assertFalse(Category::query()->where('slug', 'fara-categorie')->exists());

        // Fiecare produs are categoria si specia din clasificare.
        $classification = $importer->classification();
        foreach (Product::query()->with(['categories', 'attributeValues.attribute'])->get() as $p) {
            $c = $classification[(string) $p->wc_product_id];
            $this->assertEqualsCanonicalizing($c['categories'], $p->categories->pluck('path')->all(), $p->name);
            $this->assertEqualsCanonicalizing($c['species'], $p->attributeValues->where('attribute.slug', 'animal')->pluck('name')->all(), $p->name);
            $this->assertSame($c['categories'][0], $p->categories->firstWhere('pivot.is_primary', true)?->path);
        }

        // Adresele vechi ale categoriilor duc permanent la cele noi.
        $this->assertGreaterThan(100, $stats['redirecturi']);
        $this->get('/categorie-produs/echipamente-veterinare/')->assertRedirect(site_url('/categorie-produs/cabinet-veterinar/echipamente/'));
        $this->get('/categorie-produs/hrana/page/2/')->assertRedirect(site_url('/categorie-produs/hrana/').'?page=2');

        // Filtrul „Animal” apare in catalog.
        $this->get('/categorie-produs/caini/')->assertOk()->assertSee('Animal');
    }
}
