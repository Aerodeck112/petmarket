# Manifestul versiunii 0.1.2

| | |
|---|---|
| Versiune | 0.1.2 (28.11.2026) – SEO, accesibilitate, conformitate legală |
| Cod sursă | ramura `claude/confident-gauss-9ogkxt` |

## Pachetul de actualizare → 0.1.2

| | |
|---|---|
| Fișier | `petmarket-update-0.1.2.zip` (semnat; se aplică peste orice 0.1.x: 0.1.0 sau 0.1.1) |
| Dimensiune | 622 958 octeți |
| Fișiere | 78 (tot ce s-a schimbat după primul pachet 0.1.0; include și 0.1.1) |
| Migrări | `add_sale_restriction_to_products`, `apply_sale_restrictions_to_imported_products`, `add_unit_price_and_product_safety`, `publish_legal_documents` |
| SHA-256 | `968146e4e78e778bc48399db0ac719ca652637a74bc6d66934ef7a6c3bbb0779` |
| Verificare | Apache 2.4 + PHP 8.3 + MariaDB, aplicația în `public_html/petmarket`, peste o instalare 0.1.1 și peste una din 25.11 + 0.1.1; crawl complet (7.148 de adrese, 0 erori, 0 resurse lipsă); Lighthouse pe 6 tipuri de pagini, mobil și desktop: SEO 100, accesibilitate 100, best practices 100; aranjare verificată pe 168 de pagini la 390 px și 1366 px |

## Pachetul de instalare 0.1.2

| | |
|---|---|
| Fișier | `petmarket-install-0.1.2.zip` |
| Dimensiune | 46 470 444 octeți |
| SHA-256 | `80bc6af98c272c51863ea4dd4c9bf3321a61999c8a280bcc4b2c0545273a0de4` |

---

# Manifestul versiunii 0.1.1

| | |
|---|---|
| Versiune | 0.1.1 (27.11.2026) – produsele cu rețetă nu se mai vând online |
| Cod sursă | ramura `claude/confident-gauss-9ogkxt` |

## Pachetul de actualizare 0.1.0 → 0.1.1

| | |
|---|---|
| Fișier | `petmarket-update-0.1.1.zip` (incremental, semnat cu cheia Pet Market) |
| Dimensiune | 483 083 octeți |
| Fișiere | 56 (tot ce s-a schimbat după primul pachet 0.1.0, deci se potrivește oricărei instalări 0.1.0) |
| Migrări | `add_sale_restriction_to_products`, `apply_sale_restrictions_to_imported_products` |
| SHA-256 | `0f8b0c4f8a62115676ca7262b3a03ca5e3161fbaf7c8e5cd889d381dec39f73b` |
| Verificare | Apache 2.4 + PHP 8.3 + MariaDB, aplicația în `public_html/petmarket`: (1) peste o instalare din pachetul din 25.11 (fără restricții, produse importate): 26 de fișiere schimbate, ambele migrări rulate, 598 de produse scoase de la vânzarea online, site și admin funcționale; (2) peste o instalare care avea deja restricțiile: doar migrarea de date, restricția setată manual din admin păstrată |

Se aplică din admin: *Sistem → Actualizări → Încarcă pachet de actualizare*, sau prin File Manager în
`storage/app/private/updates` și butonul „Pachet urcat prin File Manager”. Înainte se face automat backup
la baza de date; la o eroare, fișierele și baza revin singure.

## Pachetul de instalare 0.1.1

| | |
|---|---|
| Fișier | `petmarket-install-0.1.1.zip` |
| Dimensiune | 46 451 730 octeți (aproximativ 44 MB) |
| SHA-256 | `ab3ba9b69d7efe1368aa0ae3870b11d0b86b48e2b34a45ae5d34c085c7820e6e` |

Pentru o instalare nouă se folosește direct pachetul 0.1.1; pachetele de imagini rămân aceleași (vezi mai jos).

---

# Manifestul versiunii 0.1.0

| | |
|---|---|
| Versiune | 0.1.0 (de probă; neinstalată în producție) |
| Data | 27.11.2026 (instalare fără Terminal, în public_html) |
| Cod sursă | ramura `claude/confident-gauss-9ogkxt` |
| PHP minim | 8.3.0 |
| Bază de date | MySQL 8 / MariaDB 10.6+ (testat pe MariaDB 10.11 și SQLite) |
| Framework | Laravel 13, Filament 5, Livewire 4 |

## Pachetul de instalare

| | |
|---|---|
| Fișier | `petmarket-install-0.1.0.zip` |
| Dimensiune | 46 450 499 octeți (aproximativ 44 MB) |
| Fișiere | 16 690 |
| SHA-256 | `3a9261dd9b8cc43504b443f8b93ddf23969e20cf8af66f208fe94bd01afbfdf2` |
| Verificare | instalare directă simulată (PHP 8.3 + MariaDB 10.11, WordPress mutat în `public_html_vechi`): instalator complet, import 5.724 de produse, prețuri și stoc din baza WooCommerce, imagini copiate de pe disc; pachet de actualizare 0.1.1 semnat cu cheia nouă acceptat, pachet modificat respins; catalogul curățat reimportat integral (5.724 de produse, 137 de categorii, 0 fără categorie); 25.11: import din exportul CSV (5.823 de produse: 5.724 publicate, 99 ciorne) cu cele 4 pachete de imagini dezarhivate, 8.289 din 8.289 imagini locale, fără nicio legătură la site-ul vechi (pe SQLite; instalatorul nu a fost rerulat pe MariaDB pentru această reconstruire) |

Pachetul **nu este în Git** (fișier binar mare). Amprenta se schimbă la fiecare reconstruire.

Conține: codul, dependențele de producție, asseturile compilate, logo-ul și imaginile site-ului, exportul
catalogului (`database/seeders/data/catalog`, 19 MB) și ghidurile. Nu conține: date de clienți, chei,
parole, teste, dependențe de dezvoltare. Cheia publică de actualizare Pet Market este inclusă și scrisă de instalator în `.env`.

## Pachetele de imagini

Imaginile celor 5.823 de produse (7.762 de fișiere sursă), descărcate de pe pet-market.ro pe 25.11.2026 și
optimizate WebP (1000 px + miniatură 400 px), plus imaginile paginilor vechi. Se dezarhivează în folderul
principal al contului, peste `petmarket/`; la import se leagă automat de produse (verificat: 8.289 din 8.289).

| Fișier | Octeți | SHA-256 |
|---|---|---|
| `petmarket-imagini-1-din-4.zip` | 90 350 297 | `8820f30293c9b29cce077a21d15a123d0b735a7109481c49588d7b7eee761a13` |
| `petmarket-imagini-2-din-4.zip` | 88 192 288 | `65bc277331e485420aa6d8443ddb939b7300918573be07e1a12996870e4fc7e9` |
| `petmarket-imagini-3-din-4.zip` | 87 740 656 | `a83c24f17226a14ea15547f7505382376024cc75dc005b823ee3082408cc584f` |
| `petmarket-imagini-4-din-4.zip` | 88 289 734 | `47521b968f3c73d0d56106329d4fc3cd0a3ec79291ef9a0bb40abdaa26984d6b` |

## Reconstruire

```bash
composer install --no-dev --optimize-autoloader
```

```bash
php artisan petmarket:release install-zip
```

Fișierul `petmarket-install-0.1.0.manifest.json` din `storage/app/releases/` se păstrează: este baza
primului pachet de actualizare incremental.
