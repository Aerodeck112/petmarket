<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Page;
use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

/**
 * Metodele de livrare, paginile de baza si bannerele de pornire. Ruleaza idempotent (updateOrCreate):
 * nu suprascrie ce a fost deja editat din admin, doar completeaza ce lipseste.
 */
class ShopSeeder extends Seeder
{
    public function run(): void
    {
        // Ca in magazinul vechi: curier rapid 23 lei, gratuit de la 1.000 lei (pragul general din setari).
        $methods = [
            ['code' => 'curier', 'name' => 'Livrare cu curier rapid', 'kind' => 'courier', 'carrier' => null, 'cost' => 2300, 'eta' => '2–4 zile lucrătoare', 'sort' => 1,
                'description' => 'Coletul ajunge la adresa ta. Primești numărul AWB pe email când pleacă.'],
            ['code' => 'easybox', 'name' => 'Livrare în easybox', 'kind' => 'locker', 'carrier' => 'sameday', 'cost' => 1900, 'eta' => '1–3 zile lucrătoare', 'sort' => 2,
                'cod_allowed' => false, 'description' => 'Ridici coletul din lockerul ales, oricând.', 'is_active' => false],
            ['code' => 'ridicare', 'name' => 'Ridicare din magazin (Str. Băneasa 23, Târgu Mureș)', 'kind' => 'pickup', 'carrier' => null, 'cost' => 0, 'free_over' => 0,
                'eta' => 'în aceeași zi, în timpul programului', 'sort' => 3, 'description' => 'Te anunțăm când comanda e pregătită.', 'is_active' => false],
        ];
        foreach ($methods as $m) {
            ShippingMethod::query()->firstOrCreate(['code' => $m['code']], $m);
        }

        $pages = [
            ['slug' => 'home', 'title' => 'Acasă', 'template' => 'home'],
            ['slug' => 'contact', 'title' => 'Contact', 'template' => 'contact', 'lead' => 'Întrebări despre o comandă, un produs sau ce să alegi pentru animalul tău? Hiko și Raza te ascultă.'],
            ['slug' => 'intrebari-frecvente', 'title' => 'Întrebări frecvente', 'template' => 'faq'],
            ['slug' => 'blog', 'title' => 'Blog', 'template' => 'blog', 'lead' => 'Sfaturi de nutriție și îngrijire de la medicii HiKeVet.'],
            // Slug-urile paginilor legale sunt cele din magazinul vechi (SEO); textul se preia la import.
            ['slug' => 'termeni-conditii', 'title' => 'Termeni și condiții', 'template' => 'legal'],
            ['slug' => 'politica-confidentialitate', 'title' => 'Politica de confidențialitate', 'template' => 'legal'],
            ['slug' => 'politica-cookies', 'title' => 'Politica de cookies', 'template' => 'legal'],
            ['slug' => 'despre-noi', 'title' => 'Despre noi', 'template' => 'default'],
            ['slug' => 'livrare', 'title' => 'Livrare', 'template' => 'legal'],
            ['slug' => 'politica-de-retur', 'title' => 'Politica de retur', 'template' => 'legal'],
        ];
        foreach ($pages as $p) {
            Page::query()->firstOrCreate(['slug' => $p['slug']], $p + ['status' => 'published', 'published_at' => now(),
                'body' => $p['template'] === 'legal' ? '<p>Textul acestei pagini se preia din magazinul actual la importul catalogului (Sistem → Import catalog) sau se completează din Conținut → Pagini.</p>' : null]);
        }

        if (! Banner::query()->exists()) {
            Banner::query()->create(['placement' => 'hero', 'title' => 'Tot ce le trebuie, ales de medici veterinari', 'subtitle' => 'Hrană, recompense și accesorii pentru câini și pisici, cu sfatul echipei HiKeVet.',
                'cta_label' => 'Vezi magazinul', 'url' => '/magazin/', 'theme' => 'cobalt', 'sort' => 1]);
            Banner::query()->create(['placement' => 'hero', 'title' => 'Reduceri care dau din coadă', 'subtitle' => 'Produsele la ofertă, cu prețul cel mai mic din ultimele 30 de zile afișat cinstit.',
                'cta_label' => 'Vezi reducerile', 'url' => '/magazin/?on_sale=1', 'theme' => 'magenta', 'sort' => 2]);
        }
    }
}
