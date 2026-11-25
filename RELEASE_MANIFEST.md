# Manifestul versiunii 0.1.0

| | |
|---|---|
| Versiune | 0.1.0 (de probă; neinstalată în producție) |
| Data | 25.11.2026 (catalog pe structura veche, curățată) |
| Cod sursă | ramura `claude/confident-gauss-9ogkxt` |
| PHP minim | 8.3.0 |
| Bază de date | MySQL 8 / MariaDB 10.6+ (testat pe MariaDB 10.11 și SQLite) |
| Framework | Laravel 13, Filament 5, Livewire 4 |

## Pachetul de instalare

| | |
|---|---|
| Fișier | `petmarket-install-0.1.0.zip` |
| Dimensiune | 46370017 octeți (aproximativ 44 MB) |
| Fișiere | 16 687 |
| SHA-256 | `6549f9f189b550487697a0a111cf4977f909420ecf34e306f7a0635217954e4b` |
| Verificare | instalare directă simulată (PHP 8.3 + MariaDB 10.11, WordPress mutat în `public_html_vechi`): instalator complet, import 5.724 de produse, prețuri și stoc din baza WooCommerce, imagini copiate de pe disc; pachet de actualizare 0.1.1 semnat cu cheia nouă acceptat, pachet modificat respins; catalogul curățat reimportat integral (5.724 de produse, 137 de categorii, 0 fără categorie) |

Pachetul **nu este în Git** (fișier binar mare). Amprenta se schimbă la fiecare reconstruire.

Conține: codul, dependențele de producție, asseturile compilate, logo-ul și imaginile site-ului, exportul
catalogului (`database/seeders/data/catalog`, 19 MB) și ghidurile. Nu conține: date de clienți, chei,
parole, teste, dependențe de dezvoltare. Cheia publică de actualizare Pet Market este inclusă și scrisă de instalator în `.env`.

## Reconstruire

```bash
composer install --no-dev --optimize-autoloader
```

```bash
php artisan petmarket:release install-zip
```

Fișierul `petmarket-install-0.1.0.manifest.json` din `storage/app/releases/` se păstrează: este baza
primului pachet de actualizare incremental.
