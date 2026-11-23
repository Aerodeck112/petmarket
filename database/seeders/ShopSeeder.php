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

        // Mesajele din bula de pe prima pagina si bannerele promo (imaginile din magazinul vechi).
        if (! Banner::query()->exists()) {
            $hero = [
                ['Hrană super premium pentru câini', 'Pentru câini adulți de toate rasele, aleasă de medicii HiKeVet.', 'Vezi hrana pentru câini', '/categorie-produs/hrana/caini-hrana/', 'cobalt'],
                ['Tot ce le trebuie pisicilor', 'Hrană, diete veterinare și accesorii pentru pisici fericite.', 'Vezi produsele pentru pisici', '/categorie-produs/hrana/pisici-hrana/', 'cobalt'],
                ['Echipamente veterinare', 'Produse destinate clinicilor și cabinetelor veterinare.', 'Vezi echipamentele', '/categorie-produs/echipamente-veterinare/', 'cobalt'],
            ];
            foreach ($hero as $i => [$title, $sub, $cta, $url, $theme]) {
                Banner::query()->create(['placement' => 'hero', 'title' => $title, 'subtitle' => $sub, 'cta_label' => $cta, 'url' => $url, 'theme' => $theme, 'sort' => $i + 1]);
            }
            $promo = [
                ['Antiparazitare', 'Protecție contra puricilor și căpușelor.', '/categorie-produs/otc/antiparazitare-otc/', 'media/site/banners/antiparazitare.webp', 'sun'],
                ['Diete veterinare', 'Monge VetSolution și alte diete recomandate de medic.', '/categorie-produs/diete-2/', 'media/site/banners/monge-vetsolution.webp', 'magenta'],
                ['Accesorii', 'Covorașe, boluri, lese și tot ce mai trebuie acasă.', '/categorie-produs/accesorii/', 'media/site/banners/accesorii.webp', 'teal'],
            ];
            foreach ($promo as $i => [$title, $sub, $url, $img, $theme]) {
                Banner::query()->create(['placement' => 'promo', 'title' => $title, 'subtitle' => $sub, 'cta_label' => 'Vezi produsele', 'url' => $url, 'image' => $img, 'theme' => $theme, 'sort' => $i + 1]);
            }
        }
    }
}
