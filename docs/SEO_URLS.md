# SEO_URLS — pet-market.ro (structura URL actuală)

Generat automat din sitemap-urile publice (WordPress core sitemaps, fără Yoast/RankMath) și din testele HTTP făcute pe 2026-11-23.

## 1. Sitemap index

`robots.txt` → `Sitemap: https://pet-market.ro/wp-sitemap.xml` (sitemap nativ WordPress; `/sitemap_index.xml` răspunde 404 ⇒ nu există Yoast/RankMath).

| Fișier sitemap | Tip conținut | Nr. URL-uri |
|---|---|---|
| https://pet-market.ro/wp-sitemap-posts-page-1.xml | pagini WP | 16 |
| https://pet-market.ro/wp-sitemap-posts-product-1.xml | produse (1/3) | 2000 |
| https://pet-market.ro/wp-sitemap-posts-product-2.xml | produse (2/3) | 2000 |
| https://pet-market.ro/wp-sitemap-posts-product-3.xml | produse (3/3) | 1724 |
| https://pet-market.ro/wp-sitemap-taxonomies-wd_popup_cat-1.xml | categorie popup WoodMart (inutil) | 1 |
| https://pet-market.ro/wp-sitemap-taxonomies-cms_block_cat-1.xml | categorie HTML Block WoodMart (inutil) | 1 |
| https://pet-market.ro/wp-sitemap-taxonomies-product_brand-1.xml | branduri (`product_brand`, doar cele cu produse) | 29 |
| https://pet-market.ro/wp-sitemap-taxonomies-product_cat-1.xml | categorii produse | 163 |
| https://pet-market.ro/wp-sitemap-taxonomies-product_tag-1.xml | etichete produse | 179 |
| https://pet-market.ro/wp-sitemap-users-1.xml | autori (expune username-uri!) | 2 |
| **Total** | | **6115** |

Doar sitemap-urile de tip *posts* au `<lastmod>`; taxonomiile nu au. Nu există sitemap de imagini sau Google News.

## 2. Pattern-uri URL

| Tip | Pattern | Exemplu | Observații |
|---|---|---|---|
| Home | `/` | https://pet-market.ro/ | pagina statică «Home Pet» (ID 15), Elementor |
| Magazin (shop) | `/magazin/` | https://pet-market.ro/magazin/ | pagina WooCommerce Shop; `/shop/` → 404 |
| Produs | `/produs/{slug}/` | https://pet-market.ro/produs/modul-custi-ati-cu-oxigenare-pentru-pisici/ | plat, fără categorie în URL; 5724 produse; slug ASCII (0 cu diacritice); 534 slug-uri se termină cu cod numeric (ex. `-6415`); lungime max. URL 226 caractere |
| Categorie produs | `/categorie-produs/{parinte}/{copil}/{nepot}/` | https://pet-market.ro/categorie-produs/echipamente-veterinare/ | ierarhic (adâncime în sitemap: {1: 70, 2: 23, 3: 38, 4: 32}); și varianta doar cu slug-ul final (ex. `/categorie-produs/caini-hrana/`) răspunde 200 ⇒ conținut duplicat; lipsă `<link rel=canonical>` pe arhive |
| Paginare categorie | `/categorie-produs/{...}/page/{n}/` | https://pet-market.ro/categorie-produs/hrana/page/2/ | 200; 12 produse/pagină implicit |
| Brand | `/brand/{slug}/` | https://pet-market.ro/brand/bioiberica/ | taxonomia `product_brand` (WooCommerce Brands); 132 termeni, 29 cu produse |
| Etichetă produs | `/eticheta-produs/{slug}/` | https://pet-market.ro/eticheta-produs/modul-custi-ati-cu-oxigenare-pentru-pisici/ | 179 etichete, multe sunt de fapt nume de produse/branduri importate |
| Pagină | `/{slug}/` | https://pet-market.ro/termeni-conditii/ | slug-uri mixte RO/EN (`about-us`, `refund_returns`, `best-deals`, `brands`, `compare`, `sample-page`) |
| Coș | `/cos/` | https://pet-market.ro/cos/ | `/cart/` → 404 |
| Checkout | `/finalizare/` | https://pet-market.ro/finalizare/ | `/checkout/` și `/finalizare-comanda/` → 404; endpoint-uri WC: `/finalizare/order-received/{id}/?key=…`, `/finalizare/order-pay/{id}/` |
| Cont client | `/contul-meu/` | https://pet-market.ro/contul-meu/ | `?action=register`, `/contul-meu/lost-password/`; endpoint-uri standard WC (orders, edit-address, edit-account etc.) |
| Wishlist | `/wishlist/` | https://pet-market.ro/wishlist/ | WoodMart wishlist (funcționează și pentru vizitatori) |
| Compare | `/compare/` | https://pet-market.ro/compare/ | pagină goală — funcția compare nu e activă |
| Autor | `/author/{user}/` | https://pet-market.ro/author/thea/ | expune username-uri de admin (`thea`, `vitim`) — de NU replicat |
| Căutare | `/?s={q}&post_type=product` | https://pet-market.ro/?s=monge&post_type=product | caută și după SKU; un singur rezultat ⇒ 302 direct la produs |
| Filtru brand | `?filter_product_brand={term_id}` | https://pet-market.ro/magazin/?filter_product_brand=228 | widget WoodMart layered nav (doar pe /magazin/) |
| Filtru preț | `?min_price={x}&max_price={y}` | https://pet-market.ro/magazin/?min_price=0&max_price=100 | widget WooCommerce price filter |
| Sortare | `?orderby={menu_order|popularity|rating|date|price|price-desc}` | https://pet-market.ro/magazin/?orderby=price |  |
| Produse/pagină | `?per_page={9|12|18|24}` | https://pet-market.ro/magazin/?per_page=24 |  |
| Coloane grilă | `?shop_view=grid&per_row={2|3|4}` | https://pet-market.ro/magazin/?shop_view=grid&per_row=3 | nu există vizualizare listă |
| Add to cart | `/?add-to-cart={id}` | https://pet-market.ro/?add-to-cart=32136 | blocat în robots.txt |
| Short link | `/?p={id}` | https://pet-market.ro/?p=32136 | 301 → URL-ul canonic al produsului |
| RSS | `/feed/` | https://pet-market.ro/feed/ | feed WP (fără articole) |
| Store API | `/wp-json/wc/store/v1/...` | https://pet-market.ro/wp-json/wc/store/v1/products | public |

Redirecționări globale: `http://` → `https://` (301), `www.` → apex (301), URL fără slash final → cu slash (301).

Breadcrumb produs: `Acasă › produs › {nume}` — link-ul intermediar `/produs/` dă 404 (de corectat în noua platformă: `Acasă › {categorie principală} › {nume}`).

## 3. Eșantioane reale (max. 30 per tip)

### Produse (5724 în sitemap, 30 afișate)

- https://pet-market.ro/produs/ampidexalone-100-ml/
- https://pet-market.ro/produs/aviator-super-sport-20-kg/
- https://pet-market.ro/produs/batoane-cu-ou-pentru-perusi-90-g/
- https://pet-market.ro/produs/bio-petactive-puppy-trainer-spray-100ml/
- https://pet-market.ro/produs/brit-care-dog-hypoallergenic-adult-small-breed-7-kg/
- https://pet-market.ro/produs/brit-fresh-fish-and-pumpkin-adult-large-2-5-kg/
- https://pet-market.ro/produs/brit-premium-by-nature-beef-with-tripes-800-g-conserva/
- https://pet-market.ro/produs/calibra-joy-dog-salami-fish-30-g/
- https://pet-market.ro/produs/comprese-din-tifon-5x5-cm-12-straturi-nesterile-100-buc-set-cvet/
- https://pet-market.ro/produs/credelio-225-mg-5-5-11-kg-3-tablete/
- https://pet-market.ro/produs/cusca-transport-avior-plastic-57-5x39-5x40-5cm/
- https://pet-market.ro/produs/departator-bucal-pentru-caini-talie-mare-15-cm-cvet/
- https://pet-market.ro/produs/hpm-adult-neutered-dog-small-toy-15kg/
- https://pet-market.ro/produs/hpm-senior-neutered-cat-3kg/
- https://pet-market.ro/produs/hrana-pentru-pisici-monge-cu-brocoli-si-mini-porumb-80g/
- https://pet-market.ro/produs/hrana-umeda-completa-gemon-chunkies-sterilised-cat-vita-415g/
- https://pet-market.ro/produs/hunter-ham-hilo-confort-xs-3/
- https://pet-market.ro/produs/hunter-lesa-training-leash-convenience-20-1200-cm-3/
- https://pet-market.ro/produs/hunter-zgarda-hilo-vario-basic-s-m-3/
- https://pet-market.ro/produs/josera-active-nature-5-x-900-g/
- https://pet-market.ro/produs/josidog-adult-sensitive-5x900-g/
- https://pet-market.ro/produs/menforsan-spray-antiseptic-cu-clorhexidina-pentru-animale-de-companie-60-ml/
- https://pet-market.ro/produs/monge-fruit-hrana-umeda-pentru-caini-curcan-si-afine-pate-bucati-100-g-3208-x-32-buc/
- https://pet-market.ro/produs/monge-hrana-umeda-monoproteica-pentru-caini-cu-porc-150-gr-4410-x-24-buc/
- https://pet-market.ro/produs/nutricarevet-pasta-de-carbune-30-ml/
- https://pet-market.ro/produs/parakill-10-ml/
- https://pet-market.ro/produs/pawise-jucarie-soricel-cu-pene/
- https://pet-market.ro/produs/rola-sterilizare-sterireel-150-mm-x-200-m-cvet/
- https://pet-market.ro/produs/signature7-cat-hrana-umeda-reteta-cu-ton-in-sos-de-aloe-vera-70-gr/
- https://pet-market.ro/produs/superior-care-white-dogs-grain-free-smallmini-breeds-junior-cu-peste-alb-2/

### Categorii produse (163 în sitemap, 30 afișate)

- https://pet-market.ro/categorie-produs/antibiotice-orale/
- https://pet-market.ro/categorie-produs/biosecuritate/
- https://pet-market.ro/categorie-produs/dieta-veterinara/
- https://pet-market.ro/categorie-produs/diete-2/convalescenta-recovery/
- https://pet-market.ro/categorie-produs/diete-2/convalescenta-recovery/caini-convalescenta-recovery/
- https://pet-market.ro/categorie-produs/diete-2/convalescenta-recovery/caini-convalescenta-recovery/umeda-caini-convalescenta-recovery/
- https://pet-market.ro/categorie-produs/diete-2/convalescenta-recovery/pisici-convalescenta-recovery/uscata-pisici-convalescenta-recovery/
- https://pet-market.ro/categorie-produs/diete-2/slabire-diabet/caini/
- https://pet-market.ro/categorie-produs/diete-2/suport-articular/caini-suport-articular/
- https://pet-market.ro/categorie-produs/diete-2/suport-articular/pisici-suport-articular/
- https://pet-market.ro/categorie-produs/diete-2/suport-hepatic/caini-suport-hepatic/umeda-caini-suport-hepatic/
- https://pet-market.ro/categorie-produs/diete-2/suport-hepatic/caini-suport-hepatic/uscata-caini-suport-hepatic/
- https://pet-market.ro/categorie-produs/diete-2/suport-renal/caini-suport-renal/
- https://pet-market.ro/categorie-produs/diete-2/suport-renal/pisici-suport-renal/umeda-pisici-suport-renal/
- https://pet-market.ro/categorie-produs/diete-2/suport-urinar/caini-suport-urinar/umeda-caini-suport-urinar/
- https://pet-market.ro/categorie-produs/diete-2/suport-urinar/caini-suport-urinar/uscata-caini-suport-urinar/
- https://pet-market.ro/categorie-produs/diete-2/suport-urinar/pisici-suport-urinar/
- https://pet-market.ro/categorie-produs/diete-veterinare/
- https://pet-market.ro/categorie-produs/echipamente-veterinare/
- https://pet-market.ro/categorie-produs/hrana/caini-hrana/hrana-umeda-caini-hrana/
- https://pet-market.ro/categorie-produs/hrana/pisici-hrana/hrana-monoproteica-pisici-hrana/
- https://pet-market.ro/categorie-produs/otc/afectiuni-cardiovasculare/
- https://pet-market.ro/categorie-produs/otc/afectiuni-dermatologice/spot-on/
- https://pet-market.ro/categorie-produs/otc/afectiuni-digestive/caine-afectiuni-digestive/
- https://pet-market.ro/categorie-produs/otc/afectiuni-digestive/pisica-afectiuni-digestive/
- https://pet-market.ro/categorie-produs/otc/afectiuni-osteo-articulare/caine-afectiuni-osteo-articulare/
- https://pet-market.ro/categorie-produs/otc/afectiuni-renale/caine/
- https://pet-market.ro/categorie-produs/unguente/
- https://pet-market.ro/categorie-produs/vitamine-per-os/
- https://pet-market.ro/categorie-produs/vitamine/

### Branduri (29 în sitemap, 29 afișate)

- https://pet-market.ro/brand/bioiberica/
- https://pet-market.ro/brand/virbac/
- https://pet-market.ro/brand/antibiotice-iasi/
- https://pet-market.ro/brand/aditi/
- https://pet-market.ro/brand/ceva/
- https://pet-market.ro/brand/zoetis/
- https://pet-market.ro/brand/msd/
- https://pet-market.ro/brand/elanco/
- https://pet-market.ro/brand/monge/
- https://pet-market.ro/brand/gemon/
- https://pet-market.ro/brand/bwild/
- https://pet-market.ro/brand/macs/
- https://pet-market.ro/brand/natural-greatness/
- https://pet-market.ro/brand/agil/
- https://pet-market.ro/brand/puffins/
- https://pet-market.ro/brand/puffins-naturals/
- https://pet-market.ro/brand/joe-pepper/
- https://pet-market.ro/brand/tundra/
- https://pet-market.ro/brand/necon/
- https://pet-market.ro/brand/q-pet/
- https://pet-market.ro/brand/crida-pharm/
- https://pet-market.ro/brand/mitzy/
- https://pet-market.ro/brand/petkult/
- https://pet-market.ro/brand/bosch/
- https://pet-market.ro/brand/enjoy/
- https://pet-market.ro/brand/schesir/
- https://pet-market.ro/brand/devora/
- https://pet-market.ro/brand/douxo/
- https://pet-market.ro/brand/artu/

### Etichete produse (179 în sitemap, 30 afișate)

- https://pet-market.ro/eticheta-produs/allerderm/
- https://pet-market.ro/eticheta-produs/amodip/
- https://pet-market.ro/eticheta-produs/aparat-unirar/
- https://pet-market.ro/eticheta-produs/articulatii/
- https://pet-market.ro/eticheta-produs/atopivet/
- https://pet-market.ro/eticheta-produs/batoane-dentare/
- https://pet-market.ro/eticheta-produs/caine/
- https://pet-market.ro/eticheta-produs/carucior-medical-auxiliar-cu-sertare/
- https://pet-market.ro/eticheta-produs/centrifuga-de-mare-viteza/
- https://pet-market.ro/eticheta-produs/clasic-spray/
- https://pet-market.ro/eticheta-produs/collar/
- https://pet-market.ro/eticheta-produs/condroprotectoare/
- https://pet-market.ro/eticheta-produs/feliway-optimum/
- https://pet-market.ro/eticheta-produs/fortekor/
- https://pet-market.ro/eticheta-produs/hapy-snack/
- https://pet-market.ro/eticheta-produs/hepatic/
- https://pet-market.ro/eticheta-produs/injectabile/
- https://pet-market.ro/eticheta-produs/joe-pepper/
- https://pet-market.ro/eticheta-produs/lampi-chirurgicale-cu-brat-dublu/
- https://pet-market.ro/eticheta-produs/pronefra/
- https://pet-market.ro/eticheta-produs/puffins-naturals/
- https://pet-market.ro/eticheta-produs/refractometru-veterinar/
- https://pet-market.ro/eticheta-produs/semiumeda/
- https://pet-market.ro/eticheta-produs/sigilator-pungi-pentru-sterilizare/
- https://pet-market.ro/eticheta-produs/sistem-de-incalzire-cu-aer-cald/
- https://pet-market.ro/eticheta-produs/supliment-digestiv/
- https://pet-market.ro/eticheta-produs/supliment/
- https://pet-market.ro/eticheta-produs/tundra/
- https://pet-market.ro/eticheta-produs/vikaly/
- https://pet-market.ro/eticheta-produs/virbac/

### Pagini (16 în sitemap, 16 afișate)

- https://pet-market.ro/sample-page/
- https://pet-market.ro/politica-confidentialitate/
- https://pet-market.ro/cos/
- https://pet-market.ro/finalizare/
- https://pet-market.ro/contul-meu/
- https://pet-market.ro/refund_returns/
- https://pet-market.ro/
- https://pet-market.ro/about-us/
- https://pet-market.ro/best-deals/
- https://pet-market.ro/brands/
- https://pet-market.ro/compare/
- https://pet-market.ro/wishlist/
- https://pet-market.ro/magazin/
- https://pet-market.ro/termeni-conditii/
- https://pet-market.ro/livrare/
- https://pet-market.ro/contact/

### Autori (2 în sitemap, 2 afișate)

- https://pet-market.ro/author/thea/
- https://pet-market.ro/author/vitim/

### Categorii — listă completă (pentru redirecturi 301)

Lista completă a categoriilor, cu `id`, `parent`, `slug` și `permalink`, se află în `database/seeders/data/catalog/categories.json`; lista completă a produselor (cu `permalink`) în `products-001.json`. Pentru migrare: păstrați exact aceleași path-uri `/produs/{slug}/` și `/categorie-produs/{cale}/` sau generați 301 din aceste fișiere.

