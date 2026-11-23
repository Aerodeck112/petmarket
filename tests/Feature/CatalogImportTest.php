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
}
