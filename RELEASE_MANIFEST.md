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
| Dimensiune | 46 318 146 octeți (aproximativ 44 MB) |
| Fișiere | 16 680 |
| SHA-256 | `57353ea4f118ab4c8cae8ce4dd295989ab11fecb4cf9b2561ba839ca78cb2f40` |
| Verificare | instalat din browser (PHP 8.3 + MariaDB 10.11): cod de acces, verificări, bază de date, tabele, administrator; apoi importul celor 5.724 de produse din admin (aprox. 35 de secunde) și copierea imaginilor |

Pachetul **nu este în Git** (fișier binar mare). Amprenta se schimbă la fiecare reconstruire.

Conține: codul, dependențele de producție, asseturile compilate, logo-ul și imaginile site-ului, exportul
catalogului (`database/seeders/data/catalog`, 19 MB) și ghidurile. Nu conține: date de clienți, chei,
parole, teste, dependențe de dezvoltare. `PETMARKET_UPDATE_PUBLIC_KEY` este gol (vezi `UPDATES.md` §1).

## Reconstruire

```bash
composer install --no-dev --optimize-autoloader
```

```bash
php artisan petmarket:release install-zip
```

Fișierul `petmarket-install-0.1.0.manifest.json` din `storage/app/releases/` se păstrează: este baza
primului pachet de actualizare incremental.
