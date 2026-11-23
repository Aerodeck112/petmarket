# Manifestul versiunii 0.1.0

| | |
|---|---|
| Versiune | 0.1.0 (de probă; neinstalată în producție) |
| Data | 23.11.2026 |
| Cod sursă | ramura `claude/confident-gauss-9ogkxt` |
| PHP minim | 8.3.0 |
| Bază de date | MySQL 8 / MariaDB 10.6+ (testat pe MariaDB 10.11 și SQLite) |
| Framework | Laravel 13, Filament 5, Livewire 4 |

## Pachetul de instalare

| | |
|---|---|
| Fișier | `petmarket-install-0.1.0.zip` |
| Dimensiune | 46 331 278 octeți (aproximativ 44 MB) |
| Fișiere | 16 685 |
| SHA-256 | `6abe8dbd48337eb0cb170868ce7bf07e9ae1112e9c252911419ee4d3ef891ed6` |
| Verificare | instalare directă simulată (PHP 8.3 + MariaDB 10.11, WordPress mutat în `public_html_vechi`): instalator complet, import 5.724 de produse, prețuri și stoc din baza WooCommerce, imagini copiate de pe disc; pachet de actualizare 0.1.1 semnat cu cheia nouă acceptat, pachet modificat respins |

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
