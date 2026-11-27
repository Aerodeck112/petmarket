<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedShop();
    }

    public function test_public_pages_render_with_hiko_and_raza(): void
    {
        $cat = Category::query()->create(['name' => 'Hrană', 'slug' => 'hrana', 'path' => 'hrana']);
        $brand = Brand::query()->create(['name' => 'Monge', 'slug' => 'monge']);
        $p = $this->product(['name' => 'Monge Adult 2 kg', 'brand_id' => $brand->id, 'sku' => 'MON-2']);
        $p->categories()->attach($cat);

        foreach (['/', '/magazin', '/categorie-produs/hrana', '/brand/monge', '/branduri', $p->path(), '/cos', '/contul-meu/autentificare', '/contact',
            '/livrare', '/termeni-conditii', '/sitemap.xml', '/sitemap-produse-1.xml', '/robots.txt', '/feed/google-merchant.xml'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertSee('Recompensă pentru Hiko')->assertSee('Pet Market');
        $this->get('/cos')->assertSee('buddy-name', false);
        $this->get('/categorie-produs/hrana')->assertSee('Monge Adult 2 kg')->assertSee('1 produs');
        $this->get('/nu-exista-pagina')->assertNotFound();
    }

    public function test_search_filters_and_suggestions(): void
    {
        $cat = Category::query()->create(['name' => 'Hrană', 'slug' => 'hrana', 'path' => 'hrana']);
        $a = Brand::query()->create(['name' => 'Brit', 'slug' => 'brit']);
        $b = Brand::query()->create(['name' => 'Josera', 'slug' => 'josera']);
        $this->product(['name' => 'Brit Care Adult', 'brand_id' => $a->id, 'regular_price' => 5000])->categories()->attach($cat);
        $this->product(['name' => 'Josera Active', 'brand_id' => $b->id, 'regular_price' => 20000])->categories()->attach($cat);

        $this->get('/categorie-produs/hrana?filter_brand='.$a->id)->assertSee('Brit Care Adult')->assertDontSee('Josera Active');
        $this->get('/categorie-produs/hrana?max_price=100')->assertSee('Brit Care Adult')->assertDontSee('Josera Active');
        $this->get('/cautare?q=josera')->assertSee('Josera Active')->assertDontSee('Brit Care Adult');
        $this->getJson('/cautare/sugestii?q=brit')->assertOk()->assertJsonPath('products.0.name', 'Brit Care Adult');
        $this->get('/?s=brit&post_type=product')->assertRedirect(site_url('/cautare/').'?q=brit');
    }

    public function test_hidden_and_draft_products_are_not_public(): void
    {
        $draft = $this->product(['status' => 'draft']);
        $this->get($draft->path())->assertNotFound();
        $this->actingAs($this->staff(Role::Admin))->get($draft->path())->assertOk()->assertSee('Previzualizare');
    }

    public function test_admin_screens_open_for_staff_and_are_closed_for_customers(): void
    {
        $order = $this->placeOrder();
        $admin = $this->staff(Role::SuperAdmin);
        foreach (['/admin', '/admin/orders', '/admin/orders/'.$order->id, '/admin/products', '/admin/products/create', '/admin/products/'.$order->items->first()->product_id.'/edit',
            '/admin/categories', '/admin/brands', '/admin/attributes', '/admin/coupons', '/admin/coupons/create', '/admin/customers', '/admin/reviews',
            '/admin/shipping-methods', '/admin/banners', '/admin/newsletter', '/admin/stock', '/admin/shop-settings', '/admin/catalog-import',
            '/admin/marketing-settings', '/admin/staff', '/admin/pages', '/admin/documente/comanda/'.$order->id.'/proforma', '/admin/documente/comanda/'.$order->id.'/aviz'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($this->staff(Role::Warehouse))->get('/admin/products')->assertForbidden();
        $this->actingAs($this->staff(Role::Warehouse))->get('/admin/orders')->assertOk();
        $this->actingAs($this->customer())->get('/admin')->assertForbidden();
    }

    public function test_www_host_redirects_permanently_to_the_canonical_domain(): void
    {
        $this->seedShop();
        config(['app.url' => 'https://pet-market.ro']);
        $this->get('http://www.pet-market.ro/categorie-produs/hrana/?page=2')->assertRedirect('https://pet-market.ro/categorie-produs/hrana/?page=2')->assertStatus(301);
        $this->get('https://pet-market.ro/')->assertOk();
    }
}
