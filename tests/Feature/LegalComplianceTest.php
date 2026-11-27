<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Page;
use App\Support\LegalDocuments;
use App\Support\UnitPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cerințele legale pentru un magazin online din România, verificate pe paginile publice. */
class LegalComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedShop();
    }

    public function test_net_quantity_is_detected_from_the_product_name(): void
    {
        $this->assertSame([10.0, 'kg'], UnitPrice::detect('Josera Help Heart Dog Dry 10 kg'));
        $this->assertSame([1.02, 'kg'], UnitPrice::detect('Brit GF Veterinary Diet Cat Pouch 12×85 g'));
        $this->assertSame([2.4, 'kg'], UnitPrice::detect('Monge VetSolution Recovery hrană umedă, 100 g – 4656 x 24 buc'));
        $this->assertSame([8.0, 'kg'], UnitPrice::detect('Royal Canin Mini Adult 2-10 kg 8kg'));
        $this->assertSame([0.25, 'l'], UnitPrice::detect('Brit Care Salmon Oil 250 ml'));
        $this->assertNull(UnitPrice::detect('Lesă reglabilă roșie'));
        $this->assertNull(UnitPrice::detect('Bio PetActive Bio Urinamin 40 Tabs 0.30 gr'));
        // O cantitate netă 0 (introdusă greșit) nu strică pagina: prețul pe unitate pur și simplu nu apare.
        $this->assertNull(UnitPrice::label($this->product(['net_quantity' => 0, 'net_unit' => 'kg'])));
        $this->assertTrue(UnitPrice::eligiblePaths(['hrana/caini-hrana/hrana-uscata-caini-hrana']));
        $this->assertFalse(UnitPrice::eligiblePaths(['accesorii/caini-accesorii', 'otc/antiparazitare-otc/caini-externe']));
    }

    public function test_product_page_shows_unit_price_and_manufacturer_safety_information(): void
    {
        $brand = Brand::query()->create(['name' => 'Josera', 'slug' => 'josera', 'manufacturer_info' => "JOSERA GmbH & Co. KG\nIndustriegebiet Süd, 63924 Kleinheubach, Germania"]);
        $product = $this->product(['name' => 'Josera Adult 15 kg', 'regular_price' => 30000, 'net_quantity' => 15, 'net_unit' => 'kg', 'brand_id' => $brand->id,
            'gtin' => '4032254745549', 'safety_info' => 'A se păstra la loc uscat.']);

        $this->get($product->path())->assertOk()
            ->assertSee('Preț pe unitate')->assertSee('20,00 lei/kg')
            ->assertSee('Producător și siguranță')->assertSee('Kleinheubach')->assertSee('A se păstra la loc uscat.')->assertSee('EAN 4032254745549');
    }

    public function test_checkout_button_states_the_obligation_to_pay_and_footer_has_legal_links(): void
    {
        $product = $this->product();
        $this->postJson('/cos/adauga', ['product_id' => $product->id, 'qty' => 1])->assertOk();
        $this->get('/finalizare/')->assertOk()->assertSee('Comandă cu obligație de plată');

        $this->get('/')->assertOk()
            ->assertSee('reclamatiisal.anpc.ro')->assertDontSee('ec.europa.eu/consumers/odr')
            ->assertSee('/formular-retragere/')->assertSee('Setări cookie-uri')
            ->assertSee('Str. Băneasa 23');
    }

    public function test_legal_documents_are_published_and_only_unversioned_pages_are_replaced(): void
    {
        foreach (['termeni-conditii', 'politica-de-retur', 'formular-retragere', 'livrare', 'politica-confidentialitate', 'politica-cookies'] as $slug) {
            $page = Page::query()->where('slug', $slug)->firstOrFail();
            $this->assertSame(LegalDocuments::VERSION, $page->document_version, $slug);
            $this->get('/'.$slug.'/')->assertOk()->assertSee('FEST4PETS SRL');
        }
        $this->get('/politica-de-retur/')->assertSee('în cel mult <strong>14 zile</strong>', false)->assertSee('Prin prezenta vă informez');
        $this->get('/politica-cookies/')->assertSee('pm_cart')->assertSee('_ga');

        // Actualizarea: textul vechi (fără versiune) se înlocuiește; textul editat din admin, cu versiune, rămâne.
        Page::query()->where('slug', 'livrare')->update(['body' => '<p>text vechi</p>', 'document_version' => null]);
        Page::query()->where('slug', 'termeni-conditii')->update(['body' => '<p>text editat de magazin</p>', 'document_version' => 'v-magazin']);
        Page::query()->where('slug', 'formular-retragere')->delete();
        (require database_path('migrations/2026_11_28_000002_publish_legal_documents.php'))->up();

        $this->assertStringContainsString('Unde livrăm', Page::query()->where('slug', 'livrare')->value('body'));
        $this->assertSame('<p>text editat de magazin</p>', Page::query()->where('slug', 'termeni-conditii')->value('body'));
        $this->assertTrue(Page::query()->where('slug', 'formular-retragere')->exists());
    }
}
