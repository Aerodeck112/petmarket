# Audit funcțional — pet-market.ro (WooCommerce + WoodMart)

> Scop: inventarul complet al funcționalităților și regulilor de business ale magazinului actual, ca bază pentru reconstrucția în **Laravel 13 + Filament 5**.
> Metodă: exclusiv HTTP public, neautentificat (HTML, WooCommerce Store API, WP REST API, sitemap-uri, CSS). Fără login, fără URL-uri de administrare, fără comenzi plasate (s-au creat doar coșuri temporare prin Store API pentru a citi tarifele de livrare și metodele de plată).
> Data auditului: **23.11.2026**. Toate afirmațiile au dovada (URL / fragment HTML / câmp API) alături.

Fișiere conexe generate:

| Fișier | Conținut |
|---|---|
| `database/seeders/data/catalog/products-001.json` | toate cele 5.724 de produse din Store API (19,3 MB) |
| `database/seeders/data/catalog/variations-001.json` | gol (`[]`) — nu există produse variabile |
| `database/seeders/data/catalog/categories.json` | 173 categorii (id, parent, count, slug, permalink; nicio imagine/descriere) |
| `database/seeders/data/catalog/attributes.json` | 2 atribute globale (Color, Size), fără termeni publici |
| `database/seeders/data/catalog/tags.json` | 179 etichete |
| `database/seeders/data/catalog/brands.json` | brandurile din 4 surse (Store API, `wp/v2/product_brand`, câmpul `brands`, atributul local «Brand») |
| `database/seeders/data/pages/pages.json`, `posts.json` | 16 pagini WP (HTML complet); 0 articole |
| `database/seeders/data/pages/menus.json` | meniul principal (mega-menu), meniul dreapta, meniul mobil, meniurile din footer |
| `database/seeders/data/pages/site-settings.json` | date firmă, contact, monedă, TVA, livrare, plăți, retur, texte fixe |
| `public/media/site/` + `manifest.json` | logo, favicon, slider, bannere, iconuri, logo-uri ANPC/LibraPay |
| `docs/SEO_URLS.md` | pattern-uri URL, sitemap index, eșantioane |

---

## 0. Stack tehnic detectat

| Componentă | Versiune | Dovadă |
|---|---|---|
| WordPress | 7.1.5 | `<meta name="generator" content="WordPress 7.1.5" />` (home) |
| WooCommerce | 11.1.2 | `<meta name="generator" content="WooCommerce 11.1.2" />` |
| Temă | WoodMart 8.3.9 + `woodmart-child` (necustomizat: `style.css` are doar antetul) | `wp-content/themes/woodmart/...?ver=8.3.9`, `wp-content/themes/woodmart-child/style.css` |
| Page builder | Elementor 4.3.2 (layout-uri WoodMart Builder pentru produs/arhivă) | `<meta name="generator" content="Elementor 4.3.2; ...">`, widget-uri `wd_single_product_*`, `wd_shop_archive_*` |
| Checkout | **clasic (shortcode)**, nu blocks | `form.checkout.woocommerce-checkout`, lipsă `wp-block-woocommerce-checkout` |
| Server | HTTP/2 + HTTP/3, `cache-control: public, max-age=0`, fără header de cache plugin | headere `https://pet-market.ro/` |

---

## 1. Structura site-ului

### 1.1 Header (desktop)

Header WoodMart Builder pe 2 rânduri (`xts-header_362655-*.css`): `--wd-header-general-h: 90px` (mobil 60px, nesticky) și `--wd-header-bottom-h: 62px` (**sticky**, fundal `rgba(250,186,91,1)` = `#FABA5B`). Nu există top-bar (`--wd-top-bar-h: .00001px`) și **nu există bară „livrare gratuită” în header**.

| Rând / coloană | Element | Dovadă |
|---|---|---|
| General / stânga | Logo PNG (`/wp-content/uploads/2026/01/ChatGPT-Image-29-ian.-2026-16_10_12-e1769696626846.png`, `alt=""`) → `/` | `<a class="wd-logo wd-main-logo" href="https://pet-market.ro/">` |
| General / centru | Căutare AJAX produse, placeholder „Căutare produse”, `post_type=product`, istoric căutări (max 5), delay 300 ms | `<form class="searchform wd-style-with-bg-2 woodmart-ajax-search">`, `woodmart_settings.ajax_search_delay="300"`, `search_history_items_limit="5"` |
| General / dreapta | „Login / Register” — dropdown la hover cu formular de login (user/email + parolă, „Remember me”, „Lost your password?”, „Create an Account”) | `.wd-header-my-account.wd-event-hover`, `form#customer_login.hidden-form` |
| Bottom / stânga | **Mega-menu** principal `menu-petmarket-meniu-client` (113 elemente, 4 niveluri) | vezi 1.2 |
| Bottom / dreapta | Meniu secundar: „Despre Noi” (`/about-us/`), „Contact” (`#` → deschide popup lateral contact); wishlist cu contor; coș cu contor + subtotal („0 items / 0,00 lei”) | `#menu-header-menu-right-pets`, `.wd-header-wishlist`, `.wd-header-cart.wd-design-8` |
| — | **Fără**: telefon în header, compare, selector limbă/monedă, bară promo, top-bar | absent în HTML |

Popup contact (`#popup-3009`, WoodMart popup, animație „snap-in-right”, declanșat de `.open-contacts-right`): titlu „Ai întrebări sau vrei să afli mai multe? Hai să vorbim!”, formular Contact Form 7 (id 2497) cu câmpurile **Nume, Email, Telefon, Mesaj** (primele 3 obligatorii), buton „Trimite mesaj”; dedesubt „Număr de telefon +40 771 280 345” și „E-mail office@pet-market.ro”.

Mobil: header cu burger (`.wd-header-mobile-nav`), logo, cont; căutare full-width în rândul de jos (`.wd-header-search-form-mobile`); drawer lateral cu 2 tab-uri „Meniu” / „Categorii” (`.wd-nav-mob-tab`); **toolbar sticky jos** (`.wd-toolbar`: Menu, Lista de dorințe, Cart).

Meniul mobil (`#menu-mobile-navigation`): PET-MARKET, Despre noi, Contact, Contul meu, Politică de rambursări și returnări, Termeni și condiții, Politica de confidențialitate, Informații Livrare, Lista de dorințe, Login / Register.

### 1.2 Mega-menu (structură completă în `pages/menus.json`)

```
Lichidări de stoc            /categorie-produs/lichidari-de-stoc/   (categorie GOALĂ: „Nu a fost găsit niciun produs…”)
Hrană                        /categorie-produs/hrana/
  Câini | Pisici             → Hrană umedă, Hrană uscată, Recompense, B.A.R.F., Hrană monoproteică
Diete                        /categorie-produs/diete-2/
  Slăbire / diabet, Suport articular, Suport dermatologic / hipoalergenic, Suport renal,
  Suport hepatic, Suport digestiv, Suport urinar, Convalescență / recovery
    → Câini | Pisici → Umedă | Uscată               (4 niveluri)
Suplimente                   /categorie-produs/otc/
  Antiparazitare → Câini/Pisici – interne / externe / intern-extern
  Afecțiuni dermatologice → Otice, Spot-on, Tablete / capsule
  Afecțiuni cardiovasculare, renale (Câine/Pisică), digestive (Câine/Pisică), urinare (Câine/Pisică),
  osteo-articulare (Câine/Pisică), neurologice, oncologice, stomatologice, hepatice
Accesorii                    /categorie-produs/accesorii/
Echipamente veterinare       /categorie-produs/echipamente-veterinare/
  Cabinete / clinici veterinare, Consumabile, Instrumentar, Mobilier și inox, Cuști / internare / contenție,
  Stomatologie, Chirurgie, ATI anestezie și monitorizare, Oxigenoterapie și ICU, Iluminare operatorie și examinare
Igienă                       /categorie-produs/igiena/
```
Observație: eticheta din meniu diferă de numele categoriei („Suplimente” → categoria `otc`; „Hrană” → „Hrana”). Meniul curatoriat acoperă doar o parte din cele 173 de categorii (vezi §4).

### 1.3 Footer

Fundal `#FABA5B` (`.wd-footer { background-color: rgb(250,186,91) }`), coloane colapsabile pe mobil (`collapse_footer_widgets: "yes"`):

1. **Adresa**: „Strada Băneasa Nr. 23, Târgu Mureș”; **Orar de funcționare**: „Luni - Vineri: 08.00 - 20.00”, „Sâmbătă: 10.00 - 14.00”, „Duminică: Închis”.
2. **Categorii**: Echipamente Veterinare, Hrană, Câini, Pisici, Accesorii.
3. **Legal**: Politica de Confidențialitate, Termeni și condiții, Informații livrare, Politică de rambursări și returnări, Contact.
4. „**Disponibil ÎN CURÂND pe:**” Google Play + App Store (SVG, fără link) — aplicație mobilă anunțată.
5. Logo-uri **ANPC SAL** → `https://reclamatiisal.anpc.ro/` și **SOL** → `https://consumer-redress.ec.europa.eu/site-relocation_en?event=main.home2.show&lng=RO`.
6. „**Înscriete la Newsletter** / Fii primul!” — titlu fără formular (CSS `mc4wp` încărcat, dar niciun `<form class="mc4wp-form">` în pagină).
7. Social: Facebook `https://www.facebook.com/profile.php?id=61591455733059`, Instagram `https://www.instagram.com/petmarket.ro/` (`.elementor-social-icon-*`).
8. Copyright: „Pet-Market 2026 SC FEST4PETS SRL CUI 46349603 | Powered by VITIM” + banner „icon_securitate_LibraPay_600x60px.jpg”.

### 1.4 Homepage (`/`, pagina „Home Pet”, ID 15) — secțiuni în ordine

| # | Secțiune (widget) | Conținut | Dovadă |
|---|---|---|---|
| 1 | Slider full-width (`wd_slider`, `#slider-105`, min-height 530px, săgeți + bullets) | Slide 1: „Hrana Super Premium” / „pentru câini adulți de toate rasele!” / „Cumpără acum” → `/categorie-produs/hrana/caini-hrana/`; Slide 2: „Produse pentru pisici” / „descoperă gama completă de produse destinate pisicilor!” → `/categorie-produs/hrana/pisici-hrana/`; Slide 3: „Echipamente Veterinare” / „Produse destinate clinicilor veterinare” / „Descoperă produsele” → `/categorie-produs/echipamente-veterinare/` | `#slide-1410`, `#slide-1448`, `#slide-1480` |
| 2 | Tab-uri „TOP PRODUSE” (`wd_tabs`): „Pentru Câini” / „Pentru Pisici” | fiecare tab = 5 bannere promo (imagine + titlu + „Cumpără acum”). Câini: Hrană HiperPremium (`/?s=eddy`), Antiparazitare (`/categorie-produs/otc/antiparazitare-otc`), Hrană umedă SuperPremium (`/?s=Gemon hrană umedă pentru căței`), OTC (→ produs Atopivet collar), Accesorii (→ produs covorașe absorbante). Pisici: Descoperă produsele pentru pisici (`/?s=pisici`), OTC (`/?s=medicamente pisici`), Monge VetSolution, HPM, Tratamente pentru purici (→ produse) | `.elementor-widget-wd_banner` |
| 3 | Bandă marquee (`wd_marquee`) | „Promoții Săptămânale” · [lăbuță SVG] · „Livrare gratuită la comenzi de peste 1000 RON” · [os SVG] · „Taxă Livrare 23 RON” · [lăbuță] · „Livrare rapidă” | `.wd-marquee-content` |
| 4 | „Produse vizulizate” (sic) — produse văzute recent (`wd_products`, ascuns când e gol: `wd-hide`) | carusel | `#carousel-821` |
| 5 | „Ce este popular acum” + buton „TOP BRAND-URI” (`href="#"`, mort) | — | `wd_title` + `wd_button` |
| 6 | Listă branduri (`wd_products_brands`) | 12 linkuri text: ZOETIS, Viyo, VIRBAC, Veyx, VetPlus International, Vetos Farma, Vetoquinol, Vetagro, Vanelli, Unichem, Uab Ruvera, Tundra (ordine alfabetică descrescătoare) → `/brand/{slug}/` | `.elementor-widget-wd_products_brands` |
| 7 | „Cele mai vândute” (`wd_products`, carusel, 12 produse) | card produs complet (vezi 1.6) | `#carousel-113` |
| 8 | „PET-MARKET.RO / SUPORT DEDICAT” | „Nu știi ce să alegi? Contactează-ne și te ajutăm!” + buton „Contact” → `/contact/`; imagine pisică + fundal (`pt-cat-eat-frash-food*.jpg`) | `wd_title`, `wd_text_block`, `wd_button`, `wd_image_or_svg` |
| 9 | „Din dragoste pentru animale!” | ilustrații SVG câine + pisică | `pt-dog.svg`, `pt-cat.svg` |

Bannerele sunt capturi de ecran (`Captura-de-ecran-din-2026-06-19-…png`) fără `alt`.

### 1.5 Pagina de categorie / magazin (`/magazin/`, `/categorie-produs/…`)

Layout WoodMart Builder (widget-uri în ordine): `wd_page_heading` (titlu categorie, fundal `#0a0a0a`) → `wd_element_breadcrumbs` („Acasă › …”) → `wd_product_categories` (listă ierarhică cu TOATE categoriile + contor „N produse”, acordeon pe mobil) → `wd_sidebar` → `wd_shop_archive_result_count` („Afișez 1 - 12 din 586 de rezultate”) → buton off-canvas filtre (mobil) → `wd_shop_archive_per_page` → `wd_shop_archive_view` → `wd_shop_archive_orderby` → `wd_archive_products`.

| Funcție | Valori | Dovadă |
|---|---|---|
| Filtru preț | slider min/max (0 – 25.350 lei pe /magazin/), buton „Filtrează” | `.widget_price_filter`, `max_price data-max=25350` |
| Filtru brand | listă cu scroll (max-height 223px) + contor, URL `?filter_product_brand={id}` — **doar pe /magazin/**, nu pe categorii | `#woodmart-woocommerce-layered-nav-4` „Brand” |
| Filtre atribute / stoc / reduceri | **inexistente** | — |
| Sortare | Sortare implicită; popularitate; evaluare medie; cele mai recente; preț crescător; preț descrescător | `select.orderby` (`menu_order`, `popularity`, `rating`, `date`, `price`, `price-desc`) |
| Produse / pagină | 9 / 12 (implicit) / 18 / 24 | `?per_page=` |
| Grilă | 2 / 3 / 4 coloane, fără mod listă | `?shop_view=grid&per_row=` |
| Paginare | numerotată „1 2 3 4 … 47 48 49 →”, URL `/page/N/` | `.woocommerce-pagination` |
| AJAX shop | filtrare/paginare fără reload | `ajax_shop: "1"`, `ajax_scroll: "yes"` |
| Descriere categorie, imagine categorie | nu există (0 din 173) | `categories.json` |
| Mini-coș | widget coș în sidebar | `.widget_shopping_cart` |

### 1.6 Cardul de produs (listări, carusele)

Imagine → buton „Adaugă la favorite” (wishlist) → titlu → categorii (linkuri, separate prin virgulă) → preț + „TVA inclus” → „**Livrare estimată: 2–4 zile lucrătoare**” → buton „Adaugă în coș” (AJAX, deschide coșul lateral: `add_to_cart_action: "widget"`) → „Quick view” (popup 920px). Butoanele apar la hover (`wd-hover-buttons-on-hover`).
Produse cu rețetă: în loc de „Adaugă în coș” apar badge-ul „**Produse ce nu se vand online**” (`.pm-rx-loop-badge`) și butonul „Vezi detalii” (ex. `/categorie-produs/otc/antiparazitare-otc/`, „Evicto 120mg x 4 pipete”).
Etichete: reducere „**-31%**” (`.product-labels .onsale`, culoare `#FABA5B`); eticheta „Nou” e stilată în CSS (`.product-label.new` `#3452EF`) dar nu apare pe niciun produs; nu există „Stoc epuizat”/„Hot”.

### 1.7 Pagina de produs (`/produs/{slug}/`)

Exemplu: `https://pet-market.ro/produs/carnilove-dog-jerky-turkey-rabbit-100-g/`.

| Bloc | Comportament | Dovadă |
|---|---|---|
| Breadcrumb | „Acasă › produs › {nume}” — linkul „produs” → `/produs/` = **404** | `wd_element_breadcrumbs`; JSON-LD `BreadcrumbList` |
| Navigare prev/next | miniatură + nume + preț produs anterior/următor + „Back to products” | `.wd-single-nav` |
| Galerie | imagine principală + thumbnails (max 8 imagini/produs), zoom la hover, lightbox PhotoSwipe („Click to enlarge”) | `zoom_enable: "yes"`, `.woodmart-show-product-gallery` |
| Titlu | H1 | `h1.product_title` |
| Meta | doar „Brand: {X}” (SKU-ul **nu** e afișat, categoriile nu sunt afișate) | `.product_meta` |
| Stoc | „50 în stoc” / „1 în stoc” / „Disponibil pentru pre-comenzi” (backorder) / nimic (fără gestiune stoc) | `p.stock.in-stock` |
| Preț | „22,24 lei TVA inclus”; la reducere `<del>475,79 lei</del> <ins>329,39 lei</ins>` + eticheta „-31%” | `p.price`, `small.woocommerce-price-suffix` |
| Omnibus („cel mai mic preț din ultimele 30 de zile”) | **nu există** | căutare text în toate paginile |
| Cantitate | input number cu butoane − / +; max = stocul (ex. 50) sau 9999 fără gestiune stoc | `add_to_cart.maximum` |
| Adaugă în coș | buton „Adaugă în coș” | `.single_add_to_cart_button` |
| Livrare estimată | „Livrare estimată: 2–4 zile lucrătoare” (static, identic pe toate produsele) | text sub buton |
| Wishlist | „Adaugă la favorite” | `.wd-single-wishlist-btn` |
| Info-box | ✓ „Livrare gratuită la comenzi de peste 1000 RON” + imagine LibraPay | `check.svg`, `icon_securitate_LibraPay_600x60px.jpg` |
| Tab-uri | „Descriere”, „Informații suplimentare” (Greutate, Brand — apare doar dacă există greutate/atribute), „Recenzii (N)” | `.woocommerce-tabs` |
| Recenzii | formular WP: rating obligatoriu (Perfect/Bun/Mediu/Nu-i rău/Foarte slab), text, nume, email, checkbox salvare date; permise vizitatorilor; 0 recenzii în tot catalogul | `form#commentform` |
| Produse similare / upsell / cross-sell | **nu există** pe pagina de produs | lipsă `.related`, `.upsells` |
| Bară sticky add-to-cart | configurată (`sticky_add_to_cart_offset: 250`) | `woodmart_settings` |
| Produs cu rețetă (plugin custom `pet-market-prescriptie-blocker`) | fără buton de cumpărare; caseta „! Produse ce nu se vand online — Acest produs nu se comercializează online. Este un produs pentru care este necesară rețetă medical-veterinară, conform regimului aplicabil. Pentru detalii privind disponibilitatea, condițiile de eliberare și pașii următori, vă rugăm să ne contactați telefonic. [+40 771 280 345]” + „Acest produs nu se comercializează online. Pentru detalii, sunați la +40 771 280 345 sau scrieți la office@pet-market.ro.” | `section.pm-rx-notice` pe `/produs/simparica-trio-48mg-20-1-40kg-x-3-tab/`; Store API: `is_purchasable:false`; `POST /cart/add-item` → `woocommerce_rest_product_not_purchasable` |
| Produs nevandabil online (non-Rx) | doar textul scurt „Acest produs nu se comercializează online. Pentru detalii, sunați la …” (fără caseta Rx) | `/produs/sistem-de-incalzire-cu-aer-cald-pentru-animale/` |
| Schema.org | `Product` (name, url, description, image, sku, brand, `offers` cu `UnitPriceSpecification`, `valueAddedTaxIncluded: true`, `priceType: ListPrice` la reducere, `priceValidUntil: 2027-12-31`, `availability`) + `BreadcrumbList` | JSON-LD în `<head>` |

### 1.8 Coș (`/cos/`)

Pași checkout afișați sus: „Cărucior de cumpărături › Checkout › Order complete” (`.wd-checkout-steps`, ultimul pas netradus).
Bară progres livrare gratuită: „**Add 977,76 lei to cart and get free shipping!**” (netradus; `.wd-free-progress-bar`, prag 1.000 lei).
Tabel: produs, preț, cantitate (− / +, actualizare automată — butonul „Actualizează coșul” e ascuns `wd-hide`), subtotal, ștergere ×.
Cupon: câmp „Cod cupon” + „Aplică cuponul” (**cupoanele sunt activate**).
Totaluri: Sub-total; Livrare „Livrare cu curier rapid: 23,00 lei” + „Livrare la Mureș.” + „Modifică adresa” (calculator: țară, județ, localitate, cod poștal); Total „45,24 lei (include 3,86 lei TVA)”; buton spre `/finalizare/`.
Sub coș: carusel „Poți fi interesat de ...” (produse).
Coș lateral (off-canvas „Cărucior de cumpărături”) la adăugarea în coș.

### 1.9 Checkout (`/finalizare/`)

Checkout clasic, **doar ca vizitator** (fără login/creare cont în checkout: lipsă `createaccount` și „Ai deja cont?”). Ordinea câmpurilor (toate etichetele în română):

| Câmp | Obligatoriu | Observații |
|---|---|---|
| Ai un cupon? Dă clic aici… | — | toggle cupon |
| Prenume, Nume | da | `billing_first_name`, `billing_last_name` |
| Nume companie | opțional | **singurul câmp pentru PJ** — nu există selector PF/PJ, CUI, Reg. Com., bancă/IBAN |
| Țară/regiune | da | listă completă de țări, implicit **RO** |
| Stradă + Apartament/complex | da / opțional | placeholder „Adresă, nume stradă, număr etc.” |
| Municipiu/localitate | da | **câmp text liber** (nu listă de localități) |
| Județ | da | listă cele 41 județe + București (coduri ISO: AB, AR, AG, … `B` = București), implicit **MS (Mureș)** |
| Cod poștal | da | — |
| Telefon, Adresă email | da | — |
| Livrezi la o altă adresă? | checkbox | același set de câmpuri `shipping_*` (inclusiv Telefon) |
| Note comandă | opțional | „Note referitoare la comanda ta, de exemplu: anumite note pentru livrare.” |
| Metode livrare | — | radio/hidden `shipping_method[0]` |
| Informații plată | — | „Transfer bancar direct”, „Numerar la livrare” |
| Termeni și condiții | **nu există checkbox** | doar textul de confidențialitate cu link spre politica de confidențialitate |
| Buton | „Plasează comanda” | `#place_order` |

Rezumatul comenzii („Comanda ta”) permite modificarea cantității și ștergerea produselor direct în checkout (`.wd-checkout-remove-btn`, input `qty`).

### 1.10 Cont client (`/contul-meu/`)

Pagina afișează Login (user/email + parolă, Remember me, Lost your password?) și Register (**doar email**; „Va fi trimisă o legătură la adresa ta de email pentru a seta o parolă nouă.”; câmp honeypot anti-spam `email_2`; text GDPR). Recuperare parolă: `/contul-meu/lost-password/`. Zona autentificată nu a putut fi văzută (fără login); WooCommerce oferă implicit: Panou, Comenzi, Descărcări, Adrese, Detalii cont, Logout (+ Wishlist WoodMart).

### 1.11 Wishlist / Compare / Quick view

* **Wishlist** WoodMart la `/wishlist/` — funcționează pentru vizitatori (stocare cookie/local; „Această listă de dorințe este goală…”), contor în header și toolbar, popup la adăugare (`wishlist_show_popup: "enable"`).
* **Compare**: pagina `/compare/` există dar e goală; niciun buton „Compară” — funcție dezactivată.
* **Quick view**: activ (popup 920px) pe toate cardurile.

### 1.12 Căutare

AJAX live search (WoodMart) în header, doar produse (`post_type=product`), sugestii după 300 ms, „View all results”, istoric local 5 căutări. Pagina de rezultate `/?s={q}&post_type=product` folosește layout-ul de arhivă (titlu „Rezultatele căutării: „monge””). **Căutarea găsește și după SKU** (`?s=101112424` → produsul Carnilove); când există un singur rezultat → **302 direct la produs**.

### 1.13 Alte pagini

* `/contact/`: telefon, email, adresă, „Orar de funcționare: Zilnic 08:00 - 17:00” (diferit de footer!). Fără formular, fără hartă.
* `/about-us/` („Despre noi”): „Produse de calitate pentru animăluțe fericite!”, „Ce ne diferențiază?”, „Asistență de înaltă calitate 24/7”, „Livrare rapidă”, „Prețuri mici, calitate înaltă”.
* Resturi demo WoodMart, publice și în sitemap: `/brands/`, `/best-deals/` (texte în engleză, „Lorem”-like), `/sample-page/`, `/compare/`.
* Nu există blog (0 articole), nu există pagină de cookies (deși politica de confidențialitate trimite la „Politica de Cookie-uri”).

---

## 2. Reguli de business

### 2.1 Monedă și formatare
* RON, simbol „lei” **după** sumă, separator zecimal `,`, mii `.`, 2 zecimale: „14.430,00 lei” (Store API `prices.currency_*`: `currency_suffix: " lei"`, `currency_decimal_separator: ","`, `currency_thousand_separator: "."`, `currency_minor_unit: 2`).
* Sufix de preț „**TVA inclus**” peste tot (`<small class="woocommerce-price-suffix">TVA inclus</small>`).

### 2.2 TVA
* Prețurile sunt introduse **cu TVA inclus**; totalul arată „(include X lei TVA)”.
* Cote observate prin Store API cart: **21%** (Carnilove Jerky: `line_total 1838 + tax 386 = 22,24 lei`; Modul cuști ATI: `1192562 + 250438`) și **11%** (Atopivet 180cps: `92331 + 10156 = 1024,87 lei`). Există deci cel puțin 2 clase de taxă (standard + redusă); atribuirea clasei pe produs nu e publică.
* Livrarea **nu este taxată** (`total_shipping_tax: "0"`).

### 2.3 Livrare
* O singură zonă (România implicit, județ implicit Mureș). Metode (Store API `shipping_rates`):
  * `flat_rate:3` „**Livrare cu curier rapid**” — **23,00 lei**;
  * `free_shipping:2` „**Livrare gratuită**” — 0 lei, cu **minim 1.000 lei calculat cu TVA** (coș 999,03 lei → doar 23 lei; coș 1.024,87 lei (923,31 fără TVA) → gratuit). Când e eligibilă, apare **împreună** cu tariful de 23 lei și e **preselectată**.
* Același cost pentru toate județele testate (MS, B, CJ) — fără tarif pe greutate/zonă.
* Curierul **nu este numit** nicăieri („firme de curierat rapid”); nu există plugin Sameday/FanCourier/Cargus/DPD, nu există easybox/lockere, nu există AWB/tracking vizibil.
* Termen: „Livrare estimată: 2–4 zile lucrătoare” (card + produs) vs. „1 – 7 zile lucrătoare” (pagini legale) — **inconsecvență**.
* Refuz nejustificat al coletului → „ne rezervăm dreptul de a limita accesul la comenzi viitoare” (`/livrare/` §8).

### 2.4 Plată
* Active (Store API `payment_methods: ["bacs","cod"]`): **Transfer bancar direct** (OP; comanda nu se livrează până la încasare) și **Numerar la livrare** (ramburs).
* **Nicio plată cu cardul** activă, deși logo-ul „LibraPay – securitate” apare în footer și pe produs (LibraPay = procesatorul Libra Internet Bank); paginile legale menționează doar „plata ramburs” / „alte metode … (dacă sunt implementate)”.
* Fără comision ramburs (`fees: []`), fără valoare minimă de comandă (comanda de 22,24 lei ajunge la checkout), fără valoare maximă pentru ramburs.

### 2.5 Promoții, cupoane, fidelizare
* Cupoane: activate (câmp pe coș și checkout; Store API `/cart/apply-coupon`). Lista cupoanelor nu e publică.
* Reduceri: 1 singur produs la reducere (Monge Breeders 15 kg: 475,79 → 329,39 lei, „-31%”). Datele de început/sfârșit ale reducerii nu sunt publice.
* Categoria „Lichidări de stoc” există în meniu dar e goală.
* „Promoții Săptămânale” doar ca text în marquee.
* **Nu există**: puncte de fidelitate, ambalare cadou, gift card, discount pe cantitate, „Cumpără 2 primești 1”.

### 2.6 Stoc
* Toate cele 5.724 produse sunt `is_in_stock: true` (produsele fără stoc par ascunse sau trecute pe backorder).
* 3.794 produse cu gestiune de stoc (text „N în stoc”), din care **1.791 au exact „50 în stoc”** (pare plafon impus de importul din feed), 289 cu `low_stock_remaining` (stoc mic); 1.930 fără gestiune stoc (maxim 9.999 buc/coș).
* **45 produse pe backorder** — „Disponibil pentru pre-comenzi”, se pot comanda (ex. `/produs/stronghold-plus-60mg-10ml/`).
* Cantitatea maximă în coș = stocul disponibil (`add_to_cart.maximum`).

### 2.7 Produse cu rețetă / nevandabile online
* **78 produse** `is_purchasable: false` (OTC 50, Antiparazitare 15, Antiinflamatoare 8, Diverse 3, Echipamente 2). Prețul e afișat, dar coșul e blocat.
* Plugin custom `pet-market-prescriptie-blocker` v1.0.0 (`/wp-content/plugins/pet-market-prescriptie-blocker/assets/pm-rx-blocker.css`) — casetă `.pm-rx-notice` pe produs și badge `.pm-rx-loop-badge` în listări. Criteriul (meta/categorie/etichetă) nu e public.

### 2.8 Comenzi, facturare, statusuri
* Stări vizibile public: doar pașii „Cărucior › Checkout › Order complete”. Statusurile comenzii (în așteptare / în procesare / finalizată…) nu sunt vizibile fără cont.
* **Facturare**: niciun indiciu public de SmartBill / Oblio / FGO / Facturis (nici script, nici namespace REST, nici câmpuri CUI). Politica de confidențialitate menționează doar „emiterea documentelor fiscale”.
* Retur: **14 zile calendaristice** de la primire, cerere pe email la office@pet-market.ro (nume, nr. comandă, produse), transport retur plătit de client (excepție: produs defect/greșit), rambursare în **max. 30 de zile** „de la data comenzii” prin aceeași metodă; excluse: produse desigilate, hrană deschisă, produse deteriorate. Nu există formular de retur online.

### 2.9 Comunicare și conformitate
* **Newsletter**: Mailchimp for WP (MC4WP) instalat (`mc4wp/v1`, `mc4wp-css`), dar formularul nu e afișat în footer.
* **Cookie consent**: **inexistent** (niciun banner, niciun `gtag('consent', …)`), deși se încarcă Google Ads gtag → neconform GDPR/ePrivacy.
* **GDPR**: politică de confidențialitate (operator FEST4PETS SRL, drepturi, temeiuri), text de confidențialitate la checkout și înregistrare; nu există checkbox explicit T&C.
* **ANPC**: logo-uri SAL + SOL în footer, cu linkuri corecte.
* Fără verificare vârstă (`age_verify: "no"`).

### 2.10 Date de contact și firmă

| Câmp | Valoare | Sursă |
|---|---|---|
| Brand | PET-MARKET („Pet Market by HiKeVet”) | `<title>`, logo |
| Societate | **FEST4PETS SRL** | `/termeni-conditii/` |
| CUI | 46349603 | footer + T&C |
| Reg. Com. | J2022001060265; EUID ROONRC.J2022001060265; înființată 22.06.2022 | `/refund_returns/` |
| Sediu | Str. Băneasa 23, Spațiu Comercial 2, Târgu Mureș, jud. Mureș, 540198 | T&C |
| Telefon | +40 771 280 345 | popup contact, `tel:+40771280345` |
| Email | office@pet-market.ro | toate paginile legale |
| Program | L–V 08–20, S 10–14, D închis (footer) **vs.** „Zilnic 08:00 - 17:00” (`/contact/`) | inconsecvență |
| Social | Facebook `profile.php?id=61591455733059`, Instagram `@petmarket.ro` | footer |
| Dezvoltator | VITIM (vitim.ro) | footer |

### 2.11 Analytics / marketing

| Tag | Prezent | Dovadă |
|---|---|---|
| Google Ads | **AW-16651227502** (grup „GLA”, `send_page_view: false`) | `googletagmanager.com/gtag/js?id=AW-16651227502`, plugin Google for WooCommerce |
| Google Merchant Center | probabil (plugin `google-listings-and-ads`, namespace `wc/gla`) | wp-json |
| GA4 | nu | — |
| Meta Pixel / TikTok / Clarity / Hotjar | nu | — |
| Jetpack Stats | plugin instalat (`jetpack/v4`), dar fără script `stats.wp.com` în pagină | wp-json |

### 2.12 SEO
* Permalinks: produs `/produs/{slug}/`, categorie `/categorie-produs/{cale-ierarhică}/`, brand `/brand/{slug}/`, etichetă `/eticheta-produs/{slug}/`, shop `/magazin/` (detalii în `docs/SEO_URLS.md`).
* **Fără plugin SEO** (nici Yoast, nici RankMath, nici SEOPress): fără meta description, fără Open Graph/Twitter Card; `<title>` = „{Nume} – PET-MARKET Pentru că nu e doar un animal — e familie”; `robots: max-image-preview:large`.
* Canonical: prezent pe home și produse; **lipsă pe categorii**. Categoria e accesibilă și doar cu slug-ul final (`/categorie-produs/caini-hrana/` → 200) ⇒ duplicate.
* Sitemap nativ WP `/wp-sitemap.xml` (6.115 URL-uri; include autorii `thea`, `vitim`).
* Schema.org: `Product` + `BreadcrumbList` pe produs, `BreadcrumbList` pe categorii; fără `Organization`/`WebSite`/`SearchAction`.
* Slider-ul folosește `<h1>` pe homepage pentru „pentru câini adulți de toate rasele!” (repetat pentru slide-uri), iar titlul paginii de categorie e `<h2>`.
* `html lang="ro-RO"`; www → apex (301), http → https (301).

---

## 3. Pluginuri deduse

| Plugin | Dovadă | Rol |
|---|---|---|
| WooCommerce 11.1.2 | generator, `wc/store/v1`, `wc/v3`, `wc-admin`, `wc-analytics`, `wc/private`, `wc-admin-email`, `wc-push-notifications`, `wc-telemetry`, `wccom-site/v3` | motorul magazinului |
| WooCommerce POS | `wc/pos/v1/catalog` | vânzare la casă (POS) integrată în WooCommerce — posibil folosit în magazinul fizic din Târgu Mureș |
| WooCommerce Brands (core) | taxonomia `product_brand`, `/brand/`, `wc/store/v1/products/brands` | branduri |
| WoodMart 8.3.9 (+ child) | `themes/woodmart`, `woodmart_settings`, taxonomii `wd_popup_cat`, `cms_block_cat` | temă: header builder, mega-menu, AJAX search, wishlist, quick view, filtre, bară progres livrare gratuită, popup-uri, layout builder produs/arhivă |
| Elementor 4.3.2 (+ Elementor AI, `elementor-one`, `elementor-mcp-composer`) | generator, namespaces | page builder pentru home și pagini |
| Contact Form 7 6.1.7 | `contact-form-7/v1`, `_wpcf7=2497` | formular popup contact |
| Safe SVG | `safe-svg/v1` | upload SVG |
| Google for WooCommerce (Google Listings & Ads) | `plugins/google-listings-and-ads`, `wc/gla`, gtag AW- | feed Merchant Center + conversii Google Ads |
| WP All Import (+ probabil WooCommerce Import Add-On) | `wp-all-import/v1` | **import produse din feed-ul furnizorului** (stoc plafonat la 50, nume cu `&amp;`, etichete = nume produse, 1.176 produse „Fără categorie”) |
| Jetpack | `jetpack/v4` | statistici/securitate/backup (nu se vede în front) |
| MC4WP: Mailchimp for WordPress | `mc4wp/v1`, `mc4wp-css` | newsletter (formular neafișat) |
| All-in-One WP Migration | `ai1wm/v1` | backup / migrare site |
| WordPress Abilities API + MCP adapter | `wp-abilities/v1`, `mcp` | expunere „abilities” pentru agenți AI |
| **pet-market-prescriptie-blocker 1.0.0** (custom) | `plugins/pet-market-prescriptie-blocker/assets/pm-rx-blocker.css`, `.pm-rx-notice` | blochează vânzarea online a produselor cu rețetă |
| Absente | — | fără SEO plugin, fără cookie consent, fără plugin curier, fără procesator card, fără facturare, fără cache plugin, fără multilingv |

---

## 4. Statistici catalog (Store API, 23.11.2026)

| Indicator | Valoare |
|---|---|
| Produse publicate | **5.724** (`X-WP-Total: 5724`) |
| Tipuri | 100% `simple` (0 variabile, 0 grupate, 0 externe) ⇒ `variations-001.json` gol |
| Cumpărabile online | 5.646; **nevandabile (Rx)**: 78 |
| În stoc / epuizate / backorder | 5.724 / **0** / 45 |
| Gestiune stoc | 3.794 cu cantitate (1.791 cu „50 în stoc”), 1.930 fără |
| La reducere | **1** |
| Preț min / max / median / medie | **0,32 / 25.350,00 / 55,23 / 169,95 lei** |
| Distribuție preț | <50 lei: 2.726 · 50–100: 1.008 · 100–250: 1.164 · 250–500: 564 · 500–1.000: 174 · 1.000–5.000: 68 · >5.000: 20 |
| Fără imagine | 0; cu galerie (>1 imagine): 848; max 8 imagini |
| Fără SKU | **800**; SKU duplicate: 6 |
| Fără descriere | 634; descriere scurtă: 0 produse (lungime mediană descriere 592 caractere) |
| Fără categorie (doar „Fără categorie”, mutată sub „Hrana”) | **1.176** |
| În mai multe categorii | 516 |
| Cu greutate | 3.711; cu dimensiuni: 0 |
| Recenzii | 0 |
| Nume cu entități HTML (`&amp;`, `&#8211;`) | 920 (de decodat la import) |

**Categorii**: 173 (72 top-level, adâncime max 4), 0 cu imagine, 0 cu descriere. Coexistă două arbori: cel curatoriat din meniu (Hrana 1.646, Diete 230, OTC 105, Echipamente Veterinare 44, Igienă 30, Accesorii 3) și categoriile importate din feed (Instrumentar 1.301, Pet Food Dog 586, Pet Food Cat 321, Antiparazitare 247, Accesorii Pet 185, Vitamine câini și pisici 93, Recompense 93, Antibiotice orale 81, Vaccinuri 75, Hrana umedă 59, Derma 51, Dieta veterinară 49, Antibiotice injectabile 48, Diverse 46, …, plus 3 categorii „Prescription Diet – Canine/Feline”, „Science Plan – Canine”).

Produse pe categoria top-level (un produs numărat o dată per arbore): Instrumentar 1.301 · Pet Food Dog 586 · Hrana 470 (+1.176 „Fără categorie”) · Pet Food Cat 321 · Antiparazitare 247 · Diete 230 · Accesorii Pet 185 · OTC 105 · Vitamine câini și pisici 93 · Recompense 93 · Antibiotice orale 81 · Vaccinuri 75 · restul < 60.

**Branduri**: două sisteme paralele, fără suprapunere:
* taxonomia `product_brand`: 132 termeni, **29 cu produse**, pe 743 produse (Monge 280, VIRBAC 120, MAC’s 79, Gemon 61, Natural Greatness 27, Puffins Naturals 23, ZOETIS 22, Douxo 20, CEVA 15, BIOIBERICA 14 …);
* atributul **local** „Brand” (nu taxonomie): 135 valori pe 4.924 produse (Jorgen Kruuse 672, Covetrus 622, Calibra 300, Brit Care 233, Josera Petfood 233, Hunter 181, Brit 175, Inaba 144, Carnilove 138, Leopet 110 …).
* Total ≈ **158 branduri distincte**; 57 produse fără brand. În noua platformă: un singur model `Brand`.

**Atribute**: globale `pa_color` „Color” (count 7) și `pa_size` „Size” (count 5) — fără termeni publici, nefolosite de produse. Singurul atribut real e „Brand” (local). **Etichete**: 179 (multe = nume de produse/branduri/categorii, ex. „40buc/sac)”, „ADITI”, „afectiuni cardiovasculare”), pe 600 produse.

---

## 5. Identitate vizuală (măsurată din CSS)

Surse: `/wp-content/uploads/2026/08/xts-theme_settings_default-1786096073.css`, `xts-header_362655-1786095877.css`, `elementor/css/post-15.css`.

| Rol | Culoare | Variabilă / selector |
|---|---|---|
| Primar (butoane, linkuri active, săgeți carusel, eticheta „Nou”) | **#3452EF** `rgb(52,82,239)` | `--wd-primary-color`, `--btn-accented-bgcolor` |
| Primar hover | #2F4AD6 `rgb(47,74,214)` | `--btn-accented-bgcolor-hover` |
| Accent / secundar (bara de navigare, footer, eticheta reducere, scrollbar) | **#FABA5B** `rgb(250,186,91)` | `--wd-alternative-color`, `.whb-header-bottom`, `.wd-footer`, `.product-label.onsale` |
| Accent închis | #E1A752 | `post-15.css` |
| Crem (fundaluri secțiuni) | #F9EEDE | `post-15.css` |
| Fundal pagini | #F6F5F8 `rgb(246,245,248)` | `.wd-page-content` |
| Card produs / formulare | #FFFFFF | `--wd-prod-bg`, `--wd-form-bg` |
| Text | #767676 | `--wd-text-color` |
| Titluri | #242424 / #333333 | `--wd-title-color`, `--wd-entities-title-color` |
| Header titlu pagină | #0A0A0A | `.wd-page-title` |
| Succes / Avertizare | #459647 / #E0B252 | `--notices-success-bg`, `--notices-warning-bg` |
| Buton implicit | #F7F7F7 (hover #EFEFEF), text #333 | `--btn-default-*` |

Tipografie (Google Fonts: `family=Albert Sans:400,600|Fredoka:400,600,500`, `font-display: swap`):
* text: **Albert Sans** 400 (600 pentru bold), 16px;
* titluri, titluri produse, meniu, widget-uri: **Fredoka** 500 (și 400/600), `text-transform: capitalize` la meniu, butoane, titluri widget;
* butoane 14px, capitalize.

Forme: `--wd-brd-radius: 25px`; butoane și inputuri `border-radius: 35px` (pill); container 1.420px; carusele cu săgeți rotunde 42px (`#3452EF`, alb). Iconuri: font `woodmart-font` + SVG-uri ilustrative (lăbuță, os, câine, pisică). Logo PNG (fără variantă SVG proprie; `logo.svg` din media e logo-ul demo WoodMart, nefolosit).

---

## 6. Probleme observate (de NU replicat)

1. Texte netraduse: „Add … to cart and get free shipping!”, „Order complete”, „Login / Register”, „Sign in”, „Create an Account”, „Lost your password?”, „Remember me”, „Quick view”, „Click to enlarge”, „Back to products”, „items”, „Cart”, „My cart”.
2. Breadcrumb „produs” → 404; buton „TOP BRAND-URI” → `#`; „Contact” din header → `#` (popup).
3. Pagini demo publice și indexate (`/brands/`, `/best-deals/`, `/sample-page/`, `/compare/`) + autori în sitemap.
4. Categoria „Lichidări de stoc” goală, dar în meniu; 1.176 produse necategorizate; etichete-gunoi.
5. Inconsecvențe: termen livrare (2–4 vs 1–7 zile), program (footer vs contact), metode de plată (legal: doar ramburs; checkout: + OP), logo LibraPay fără plată card.
6. Lipsesc: cookie banner, pagina „Politica de cookies”, text Omnibus, checkbox T&C, câmpuri facturare PJ (CUI/Reg. Com.), meta description, OG tags, canonical pe categorii.
7. „Produse vizulizate” (typo), „Înscriete” (typo), imagini fără `alt`.

---

## 7. Reguli de păstrat în noua platformă

1. Moneda RON, format „1.234,56 lei” (sufix „lei”, zecimale cu virgulă, mii cu punct, 2 zecimale); prețurile se stochează în bani (int) și **includ TVA**.
2. Sufixul „TVA inclus” lângă orice preț afișat; totalul coșului/checkout-ului arată „(include X lei TVA)”.
3. Clase de TVA per produs: minim **21%** (standard) și **11%** (redusă); TVA-ul se extrage din prețul brut (preț / (1 + cotă)).
4. Livrarea nu este purtătoare de TVA (sau e scutită) — de confirmat contabil, dar comportamentul actual e `shipping_tax = 0`.
5. Metodă de livrare „Livrare cu curier rapid”: **23,00 lei**, tarif unic pentru toată România.
6. „Livrare gratuită” când **subtotalul produselor cu TVA ≥ 1.000,00 lei**; când e eligibilă, se afișează și se preselectează.
7. Bară de progres pe coș: „Mai adaugă X lei în coș pentru livrare gratuită!” (X = 1.000 − subtotal).
8. Mesaje fixe: „Livrare estimată: 2–4 zile lucrătoare” pe card și pe pagina de produs; info-box „Livrare gratuită la comenzi de peste 1000 RON”; marquee „Promoții Săptămânale / Livrare gratuită la comenzi de peste 1000 RON / Taxă Livrare 23 RON / Livrare rapidă” (toate editabile din admin).
9. Metode de plată: **Numerar la livrare (ramburs)** și **Transfer bancar (OP)** cu textele actuale; la OP comanda se livrează doar după încasare (status „în așteptare plată”).
10. Fără valoare minimă de comandă și fără comision ramburs.
11. Cupoane de reducere aplicabile în coș și checkout.
12. Preț redus = preț vechi tăiat + preț nou + etichetă „-X%” (rotunjit, culoarea accent); **adăugați textul Omnibus** „Cel mai mic preț din ultimele 30 de zile: …” (obligatoriu legal la reduceri) — necesită istoric de preț.
13. Gestiune stoc per produs: afișare „N în stoc”; cantitatea maximă în coș = stocul; produsele fără gestiune stoc → max 9.999.
14. **Backorder** permis per produs: text „Disponibil pentru pre-comenzi”, produsul se poate comanda.
15. Produse cu stoc 0 și fără backorder nu se afișează / nu se pot cumpăra (în prezent 0 produse „stoc epuizat” vizibile).
16. **Produse cu rețetă (Rx)**: flag per produs; prețul se afișează, butonul de coș e înlocuit de caseta „Produse ce nu se vand online …” cu telefonul +40 771 280 345 (tel: link); în listări badge „Produse ce nu se vand online” + buton „Vezi detalii”; API-ul/coșul refuză adăugarea.
17. Flag separat „nevandabil online” (non-Rx, ex. echipamente scumpe) cu textul scurt „Acest produs nu se comercializează online. Pentru detalii, sunați la … sau scrieți la …”.
18. Checkout ca vizitator (fără cont obligatoriu); țara implicită RO, județul implicit Mureș; lista celor 42 de județe cu codurile ISO (AB … VN, `B` = București); câmpuri obligatorii: prenume, nume, stradă, localitate, județ, cod poștal, telefon, email; opționale: companie, apartament, note comandă; „Livrezi la o altă adresă?” cu set complet de câmpuri (inclusiv telefon).
19. În rezumatul din checkout se poate modifica cantitatea și șterge produsul.
20. Text de confidențialitate la checkout și la înregistrare, cu link la `/politica-confidentialitate/`.
21. Înregistrare cont doar cu email + link de setare parolă; honeypot anti-spam; login cu email/username; „Ține-mă minte”; resetare parolă.
22. Wishlist disponibil și pentru vizitatori, cu contor în header și în toolbar-ul mobil.
23. Quick view pe cardurile de produs; adăugare în coș AJAX care deschide coșul lateral (off-canvas).
24. Căutare live (debounce 300 ms) doar în produse, inclusiv după **SKU**; istoric ultimele 5 căutări; redirect direct la produs când există un singur rezultat.
25. Listare: sortare (implicită, popularitate, rating, cele mai noi, preț ↑, preț ↓), 9/12/18/24 produse pe pagină (implicit 12), grilă 2/3/4 coloane, filtru preț (slider), filtru brand cu contoare, paginare numerotată `/page/N/`, contor „Afișez 1 - 12 din N de rezultate”.
26. Pagina de produs: galerie cu zoom + lightbox, prev/next, brand în meta, stoc, cantitate ±, tab-uri Descriere / Informații suplimentare (greutate, brand) / Recenzii (N), recenzii cu rating 1–5 (Perfect/Bun/Mediu/Nu-i rău/Foarte slab), permise vizitatorilor, cu moderare.
27. Mega-menu pe 4 niveluri conform `pages/menus.json` (inclusiv eticheta „Suplimente” → categoria `otc`), meniu secundar Despre Noi / Contact, meniu mobil cu tab-uri Meniu/Categorii.
28. Popup lateral de contact (Nume*, Email*, Telefon*, Mesaj) + telefon + email; mesajele trimise la office@pet-market.ro.
29. Homepage editabil din Filament: slider (3 slide-uri cu titlu, subtitlu, buton, link, imagine), tab-uri „TOP PRODUSE” Câini/Pisici cu câte 5 bannere, marquee, „Produse vizualizate recent”, listă branduri, „Cele mai vândute” (12), secțiunea „Suport dedicat”, „Din dragoste pentru animale!”.
30. Footer: adresă, program, categorii, linkuri legale, „Disponibil în curând pe” Google Play/App Store, ANPC SAL + SOL cu linkurile exacte, newsletter, social (Facebook, Instagram), copyright cu FEST4PETS SRL + CUI.
31. Pagini legale cu conținutul din `pages/pages.json`: Termeni și condiții, Politica de confidențialitate, Informații livrare, Politica de retur (14 zile, rambursare ≤ 30 zile, transport retur în sarcina clientului cu excepții), Contact, Despre noi.
32. URL-uri identice (sau 301): `/produs/{slug}/`, `/categorie-produs/{cale}/`, `/brand/{slug}/`, `/eticheta-produs/{slug}/`, `/magazin/`, `/cos/`, `/finalizare/`, `/contul-meu/`, `/wishlist/`, `/termeni-conditii/`, `/politica-confidentialitate/`, `/livrare/`, `/refund_returns/`, `/contact/`, `/about-us/`; `/?p={id}` → produs; http/www → https apex; slash final.
33. Sitemap XML cu toate produsele/categoriile/brandurile (fără autori); Schema.org `Product` cu `offers`, `valueAddedTaxIncluded`, `ListPrice` la reducere, `brand`, `sku`, `availability` + `BreadcrumbList`.
34. Tag Google Ads **AW-16651227502** și feed Google Merchant Center (de păstrat contul), **condiționate de consimțământ** (Consent Mode v2 + banner cookies — nou).
35. Identitate vizuală: primar #3452EF, accent #FABA5B, fundal #F6F5F8, text #767676, titluri #242424; fonturi Fredoka (titluri) + Albert Sans (text); colțuri 25px, butoane pill 35px; container 1.420px.
36. Branduri unificate într-o singură entitate (din `product_brand` + atributul local „Brand”), cu pagini `/brand/{slug}/`.
37. Importul periodic din feed-ul furnizorului (actualmente WP All Import) trebuie reprodus: actualizare preț/stoc pe SKU, decodare entități HTML în nume, plafon stoc (50?) — vezi întrebările.
38. Datele firmei (FEST4PETS SRL, CUI 46349603, J2022001060265, EUID, sediu) afișate în footer și în paginile legale.

## 8. Întrebări pentru proprietar

1. **Curier**: ce firmă livrează efectiv (Sameday, FAN Courier, Cargus, DPD, GLS)? Există cont API pentru generare AWB și tracking? Se dorește livrare la easybox / FANbox / lockere?
2. Tariful de 23 lei este unic indiferent de greutate (există produse de 18 kg și echipamente de 14.430 lei)? Există livrare internațională sau doar România? Ridicare personală din magazinul din Târgu Mureș?
3. **Plată cu cardul**: există contract LibraPay (sau Netopia / EuPlatesc / Stripe)? Avem credențialele de test/producție? Logo-ul LibraPay trebuie păstrat?
4. **Facturare**: ce program se folosește (SmartBill, Oblio, FGO, Saga)? Facturile se emit automat la comandă/plată? Seria de facturi? Se trimit pe email clientului?
5. **Persoane juridice**: este nevoie de câmpuri CUI, Reg. Com., IBAN, sediu social și de factură pe firmă? (clinicile veterinare sunt clienți pentru echipamente).
6. **Clase de TVA**: lista exactă a cotelor (21% / 11% / altele) și regula de atribuire pe categorie de produs; livrarea este scutită de TVA intenționat?
7. **Feed furnizor / stoc**: de unde vin produsele (Covetrus, Kruuse, distribuitor HiKeVet?), format feed (XML/CSV/API), frecvența importului, maparea categoriilor, de ce stocul e plafonat la 50, ce se întâmplă cu produsele scoase din feed? Există sincronizare cu stocul magazinului fizic / WooCommerce POS?
8. **Produse Rx**: după ce criteriu un produs e marcat „cu rețetă” (meta, categorie, listă furnizor)? Se dorește pe viitor un flux „rezervă cu rețetă” (upload rețetă, ridicare din farmacie/cabinet)?
9. Cele 2 produse non-Rx nevandabile (ex. sistem încălzire aer cald 7.540 lei) — sunt „preț la cerere”? Se dorește formular de cerere ofertă?
10. **Statusuri comenzi** folosite și emailurile tranzacționale (texte, expeditor, BCC către office@)? Se trimit SMS-uri?
11. **Cupoane** active (coduri, reguli, valabilitate) și eventualele reguli de discount pentru clinici/B2B (prețuri diferențiate pe rol de client?).
12. Reducerea Monge Breeders: are dată de expirare? Cum se gestionează „Promoții săptămânale”?
13. **Newsletter**: cont Mailchimp (listă, API key)? Se dorește formularul în footer și dublu opt-in?
14. **Conturi clienți existente**: câți clienți/comenzi trebuie migrați (export din admin WooCommerce, nu e public)? Se păstrează parolele (hash WP) sau se forțează resetarea?
15. **Google Ads / Merchant Center**: acces la conturi pentru a reconecta feed-ul și conversiile; se adaugă GA4 / Meta Pixel?
16. Program corect de funcționare (footer L–V 08–20, S 10–14 vs pagina Contact „Zilnic 08–17”) și termenul corect de livrare (2–4 vs 1–7 zile lucrătoare).
17. Aplicația mobilă „Disponibil în curând” — există un proiect? Este nevoie de API pentru ea?
18. Magazinul fizic (WooCommerce POS e instalat): vânzările din magazin trebuie să scadă stocul online?
19. Recenziile: se moderează manual? Se dorește verificarea „cumpărător verificat”?
20. Categoriile importate din feed (Pet Food Dog/Cat, Instrumentar, Vaccinuri etc.) vs meniul curatoriat: care arbore devine oficial? Ce facem cu cele 1.176 produse „Fără categorie” și cu cele 179 de etichete?
21. „Lichidări de stoc”: cum se populează (manual, automat după vechime/stoc)?
22. Politica de cookies (lipsește) și furnizorul de consimțământ dorit; DPO / persoană de contact GDPR.
23. Domeniu și email: se păstrează office@pet-market.ro? Cine administrează DNS-ul și SMTP-ul (trimitere emailuri tranzacționale)?
