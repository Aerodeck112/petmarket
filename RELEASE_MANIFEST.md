# Manifestul versiunii 0.1.0

| | |
|---|---|
| Versiune | 0.1.0 (de probă; neinstalată în producție) |
| Data | 25.11.2026 (catalog complet din exportul CSV, instalare fără site-ul vechi) |
| Cod sursă | ramura `claude/confident-gauss-9ogkxt` |
| PHP minim | 8.3.0 |
| Bază de date | MySQL 8 / MariaDB 10.6+ (testat pe MariaDB 10.11 și SQLite) |
| Framework | Laravel 13, Filament 5, Livewire 4 |

## Pachetul de instalare

| | |
|---|---|
| Fișier | `petmarket-install-0.1.0.zip` |
| Dimensiune | 46 446 141 octeți (aproximativ 44 MB) |
| Fișiere | 16 689 |
| SHA-256 | `d8f7c330205813b79bac4d75b63a89994b2297fd8822f31da700e2c2471c3588` |
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
