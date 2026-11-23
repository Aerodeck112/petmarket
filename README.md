# Pet Market by HiKeVet

Magazinul online [pet-market.ro](https://pet-market.ro), reconstruit pe platforma HiKeVet: înlocuiește
WordPress + WooCommerce + WoodMart cu un magazin propriu, cu admin mai simplu, aceleași adrese (SEO) și
identitatea HiKeVet (Hiko și Raza, culorile muralului).

Laravel 13 · PHP 8.3+ · MySQL/MariaDB · Filament 5 pentru admin · instalator cPanel și actualizări ZIP semnate.

## Stare (23.11.2026)

Versiunea 0.1.0, de probă. Rulează și este testată local (SQLite); catalogul real (5.724 de produse) a fost
importat pe o copie locală. **Nu este încă în producție** și site-ul actual nu a fost atins. Ce așteaptă
confirmări de la magazin este în [`docs/AUDIT_SITE.md`](docs/AUDIT_SITE.md), secțiunea „Întrebări pentru proprietar”.

## Pornire locală

```bash
composer install
```

```bash
cp .env.example .env && php artisan key:generate
```

```bash
touch database/database.sqlite && php artisan migrate --seed
```

```bash
php artisan petmarket:import
```

```bash
php artisan petmarket:create-admin admin@exemplu.ro "Nume Prenume"
```

```bash
php artisan serve
```

Magazinul este la `http://127.0.0.1:8000/`, adminul la `/admin`. Asseturile sunt compilate în `public/build`;
se recompilează cu `npm install && npm run build` doar când se schimbă CSS sau JS.

## Teste

```bash
php artisan test
```

## Structură

| Zonă | Unde |
|---|---|
| Logica magazinului | `app/Services/Shop` (coș, prețuri, cupoane, comenzi, stoc, emailuri, catalog) |
| Import WooCommerce | `app/Services/Import`, exportul în `database/seeders/data/catalog`, scriptul în `tools/import` |
| Admin | `app/Filament` |
| Site public | `app/Http/Controllers`, `resources/views`, `resources/css/shop.css`, `resources/js/shop.js` |
| Platformă (din HiKeVet) | `app/Services/{Install,Updates,Backup}` |

## Documente

| Document | Conținut |
|---|---|
| [`docs/AUDIT_SITE.md`](docs/AUDIT_SITE.md) | Auditul complet al magazinului actual: structură, reguli, pluginuri, statistici, întrebări |
| [`docs/SEO_URLS.md`](docs/SEO_URLS.md) | Adresele vechi și cum sunt păstrate |
| [`docs/IMPORT.md`](docs/IMPORT.md) | Importul catalogului și reîmprospătarea exportului |
| [`INSTALL_CPANEL.md`](INSTALL_CPANEL.md) | Instalarea pe cPanel și comutarea domeniului |
| [`UPDATES.md`](UPDATES.md) | Actualizări ZIP semnate, backup și restaurare |
| [`SECURITY.md`](SECURITY.md) | Securitate și GDPR |
| [`CHANGELOG.md`](CHANGELOG.md) | Ce s-a schimbat în fiecare versiune |

## Reguli care nu se negociază în cod

- Prețurile, reducerile, livrarea și TVA-ul se calculează numai pe server; coșul păstrează doar ID-uri și cantități.
- Stocul se modifică doar prin serviciul de stoc, cu o mișcare în registru; anularea unei comenzi îl restituie.
- Adresele publice ale magazinului vechi rămân valabile sau au redirect 301.
- Niciun fișier cu date reale de clienți (`.sql`, exporturi de comenzi) nu intră în Git.
