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
        // Pachetul de imagini dezarhivat inainte de import: imaginea produsului devine locala direct la import.
        $pack = public_path('uploads/products/wp/2026/04/118.jpg.webp');
        @mkdir(dirname($pack), 0755, true);
        $gd = imagecreatetruecolor(20, 10);
        imagewebp($gd, $pack);
        $importer = new CatalogImporter(base_path('tests/Fixtures/catalog-v2'));
        $stats = $importer->importTaxonomies();
        $importer->importProducts(0, 100);
        $image = Product::query()->where('wc_product_id', 4640)->first()->images()->first();
        @unlink($pack);
        $this->assertSame(['products/wp/2026/04/118.jpg.webp', 20, 10], [$image->path, $image->width, $image->height]);
        $importer->refreshCounts();

        // Structura magazinului vechi, curatata: meniul de pe pet-market.ro plus „Farmacie veterinara” pentru categoriile razlete.
        $this->assertSame(['Hrană', 'Diete', 'Suplimente', 'Accesorii', 'Igienă', 'Echipamente veterinare', 'Farmacie veterinară'],
            Category::query()->whereNull('parent_id')->orderBy('sort')->pluck('name')->all());
        $this->assertTrue(Category::query()->where('path', 'hrana/caini-hrana/hrana-uscata-caini-hrana')->exists());
        $this->assertTrue(Category::query()->where('path', 'diete-2/suport-renal/pisici-suport-renal/uscata-pisici-suport-renal')->exists());
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
        // Adresele vechi care raman in arbore merg direct; dublurile unite si „Fara categorie” au redirect permanent.
        $this->assertGreaterThan(50, $stats['redirecturi']);
        $this->get('/categorie-produs/echipamente-veterinare/')->assertOk();
        $this->get('/categorie-produs/pet-food-dog/')->assertRedirect(site_url('/categorie-produs/hrana/caini-hrana/'));
        $this->get('/categorie-produs/hrana/fara-categorie/')->assertRedirect(site_url('/categorie-produs/hrana/'));
        $this->get('/categorie-produs/antibiotice-orale/')->assertRedirect(site_url('/categorie-produs/farmacie-veterinara/antibiotice-orale/'));
        $this->get('/categorie-produs/hrana/page/2/')->assertRedirect(site_url('/categorie-produs/hrana/').'?page=2');

        // Exportul CSV din WooCommerce are prioritate: pret, promotie, stoc exact, greutate, EAN, TVA, vanzare online, ciorne.
        $p = Product::query()->where('wc_product_id', 4640)->first();
        $this->assertSame([12345, 9900, 9900, 7, 2500, '8009470012345', 11.0], [$p->regular_price, $p->sale_price, $p->price, $p->stock_qty, $p->weight_grams, $p->gtin, $p->vat_rate]);
        $this->assertTrue($p->manage_stock);
        $this->assertFalse(Product::query()->where('wc_product_id', 3586)->value('sold_online'));
        $draft = Product::query()->where('wc_product_id', 3591)->first();
        $this->assertSame('draft', $draft->status);
        $this->assertSame(\App\Enums\StockStatus::OnBackorder, $draft->stock_status);

        // Filtrul „Animal” apare in catalog.
        $this->get('/categorie-produs/otc/')->assertOk()->assertSee('Animal');
    }
}
