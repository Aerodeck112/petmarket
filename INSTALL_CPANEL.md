# Instalare pe cPanel

Ghidul descrie instalarea Pet Market dintr-un pachet ZIP, fără linie de comandă.

## Pe scurt: instalarea de la zero pe pet-market.ro

Pachetul conține tot catalogul (5.724 de produse publicate și 99 de ciorne, cu prețurile, stocul, greutatea,
EAN-ul și cota de TVA din exportul WooCommerce din 25.11.2026), iar imaginile vin într-un pachet separat,
deja optimizate. Noul magazin **nu mai are nevoie de site-ul vechi**: WordPress și baza lui se pot șterge
după ce există backup pe calculator. Site-ul este indisponibil cât durează pașii 2–6 (aproximativ o oră).

Spațiu necesar: aproximativ 160 MB aplicația, 300–350 MB imaginile, câteva zeci de MB baza de date.
Arhivele ZIP se șterg după dezarhivare.

1. **Backup pe calculator** (o singură dată, înainte de orice ștergere): folderul `wp-content/uploads`,
   fișierul `wp-config.php` și baza WordPress (cPanel → *Backup* → *Download a MySQL Database Backup*).
   Backupurile vechi lăsate în contul cPanel se descarcă și se șterg.
2. **Ștergerea site-ului vechi**: cPanel → *File Manager* → se șterge folderul `public_html` cu tot conținutul
   (dacă în el stă și alt domeniu, vezi §4), plus arhivele vechi și fișierele `error_log` din folderul principal.
   Baza WordPress se poate șterge din *MySQL Databases* după ce backupul ei e pe calculator.
3. **Bază nouă**: cPanel → *MySQL Databases* → bază goală + utilizator cu *All Privileges* (§3.2).
4. **Pachetele**: în folderul principal al contului (`/home/UTILIZATOR`) se încarcă și se dezarhivează, pe rând,
   `petmarket-install-0.1.0.zip` și pachetele de imagini `petmarket-imagini-*.zip` (toate creează / completează
   folderul `petmarket`). După fiecare dezarhivare, arhiva se șterge.
5. **Legătura site-ului** (cPanel → *Terminal*):

   ```bash
   cd ~ && ln -s petmarket/public public_html
   ```

   **Fără Terminal** (găzduire de bază, document root fix): pachetele se dezarhivează **în `public_html`**
   (rezultă `public_html/petmarket/…`), iar în `public_html` se pune fișierul `.htaccess` din
   `petmarket/deploy/public_html.htaccess` (File Manager → *Copy* în `public_html`, apoi *Rename* în `.htaccess`;
   un `.htaccess` vechi de WordPress se înlocuiește). Toate adresele ajung în `petmarket/public`, iar restul
   aplicației (`.env`, `storage`, `vendor`) nu se poate deschide din browser. Testat pe Apache 2.4 + PHP 8.3:
   instalator, site, imagini, admin, redirecturi.

6. **Instalatorul**: `https://pet-market.ro/install`, codul e în `petmarket/storage/app/install-code.txt` (§3.4).
   La SMTP pui datele căsuței `office@pet-market.ro`.
7. **Catalogul**: `/admin` → *Sistem → Import catalog*: pașii 1 și 2 (categorii, produse). Imaginile se leagă
   singure din pachetul de imagini; pasul 3 este necesar doar dacă rămân imagini necopiate, iar pasul 4 (baza
   WordPress) nu mai este necesar.
8. **Setări**: *Sistem → Setări magazin* → tabul *Ecolet* (Client ID, Client Secret, email și parolă Ecolet),
   apoi „Testează Ecolet”. IBAN-ul și datele firmei sunt deja completate.
9. **Cron** la fiecare minut (§3.5) și o comandă de test cap-coadă (plasare, email, trimitere la Ecolet).
10. **Google**: în Search Console trimiți `https://pet-market.ro/sitemap.xml`.

Ciornele (99 de echipamente din catalogul în lucru) se găsesc în admin la *Produse*, tabul *Ciorne*; se
publică de acolo când sunt gata.

## Ghidul complet

**Ce a fost verificat:** instalarea completă din pachet, în browser, pe Apache 2.4 + PHP 8.3
(mod_php) + MariaDB 10.11, cu `.htaccess` activ. **Ce nu a fost verificat:** un cont cPanel real
(LiteSpeed, CloudLinux, limitele găzduirii Pet Market). Prima instalare trebuie făcută pe un
subdomeniu de probă, nu peste site-ul actual.

## 1. Cerințe

| Cerință | Valoare |
|---|---|
| PHP | 8.3 sau mai nou (cPanel → *Select PHP Version* / *MultiPHP Manager*) |
| Extensii PHP | pdo_mysql, mbstring, openssl, gd (cu WebP), zip, intl, fileinfo, dom, curl, sodium, ctype, json, tokenizer, xml, session, filter, hash |
| Bază de date | MySQL 8 sau MariaDB 10.6+ |
| HTTPS | certificat activ (AutoSSL / Let's Encrypt) înainte de instalare |
| Spațiu | aproximativ 250 MB pentru aplicație, plus documente și backupuri |
| Cron | o sarcină pe minut |

Instalatorul verifică singur toate acestea și spune exact ce lipsește.

## 2. Structura de directoare

Pachetul se dezarhivează **în afara** directorului public. Doar `petmarket/public` este vizibil din internet.

```
/home/UTILIZATOR/
├── petmarket/                 ← aplicația (privat)
│   ├── app/  config/  database/  resources/  routes/  vendor/
│   ├── storage/             ← backupuri, jurnale, actualizări (privat)
│   ├── .env                 ← configurare și chei (privat)
│   └── public/              ← SINGURUL director servit de server
│       ├── index.php  .htaccess
│       ├── build/  media/  fonts/  js/  css/
│       └── uploads/         ← imaginile încărcate din admin
└── public_html/             ← site-ul WordPress actual; nu se atinge
```

## 3. Pași

### 3.1 Subdomeniu de probă

cPanel → *Domains* → *Create A New Domain*: de exemplu `nou.pet-market.ro`. Debifează „Share document
root” și scrie la **Document Root**: `petmarket/public`. Așteaptă certificatul SSL.

### 3.2 Bază de date

cPanel → *MySQL Databases*: creează o bază nouă și goală, un utilizator cu parolă generată și
acordă-i *All Privileges* pe acea bază. Notează numele bazei, utilizatorul și parola. Nu folosi
baza WordPress.

### 3.3 Încărcarea pachetului

cPanel → *File Manager* → directorul de bază (`/home/UTILIZATOR`) → *Upload*
`petmarket-install-X.Y.Z.zip` → *Extract*. Rezultă directorul `petmarket/`.

### 3.4 Instalatorul

1. Deschide `https://nou.pet-market.ro/`. Ești trimis la `/install`.
2. **Cod de acces.** Instalatorul cere un cod pe care îl găsești în File Manager, în
   `petmarket/storage/app/install-code.txt`. Astfel, cine nimerește adresa înaintea ta nu poate
   prelua instalarea.
3. **Verificarea serverului.** Fiecare cerință apare cu „în regulă” sau cu ce trebuie schimbat.
4. **Configurare.** Adresa site-ului, datele bazei de date și, opțional, SMTP. Conexiunea este
   testată înainte de a merge mai departe. O bază care nu este goală este refuzată.
5. **Tabele.** Se creează tabelele și se încarcă datele de bază: roluri, lista de prețuri și
   conținutul public. Nu se încarcă niciun client.
6. **Primul administrator.** Nume, email și o parolă de cel puțin 12 caractere, cu litere mari și
   mici, cifre și simboluri.

La final instalatorul se blochează: scrie `storage/app/installed.lock`, șterge codul de acces, iar
`/install` răspunde cu 404. Fișierul `.env` primește permisiunile 600.

### 3.5 Sarcina cron

cPanel → *Cron Jobs* → o sarcină **în fiecare minut** (`* * * * *`):

```bash
cd /home/UTILIZATOR/petmarket && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Calea către PHP 8.3 diferă între furnizori (de exemplu `/opt/cpanel/ea-php83/root/usr/bin/php`);
cPanel o afișează în pagina *Cron Jobs*. Această singură sarcină pornește: verificarea zilnică a
comenzilor neplătite cu cardul și curățarea coșurilor vechi (04:30), backupul zilnic (02:15), trimiterea
emailurilor din coadă, reducerile programate (la 15 minute) și curățarea linkurilor de parolă expirate. Nu este nevoie de un proces permanent.

Verificare: în admin, *Sistem → Actualizări și sănătate*, rândul „Sarcini programate (cron)”
trebuie să devină „în regulă” în câteva minute.

### 3.6 După instalare

1. Intră în `/admin` și activează autentificarea în doi pași din profil. Este disponibilă pentru
   tot personalul; **nu este impusă automat**, deci trebuie cerută fiecărui angajat.
2. *Sistem → Setări magazin*: date firmă, prag de livrare gratuită, plăți (contul IBAN pentru transfer), emailuri.
3. *Sistem → Import catalog*: importă categoriile, produsele (5.724) și copiază imaginile de pe pet-market.ro
   (vezi secțiunea 7).
3. Dacă nu ai completat SMTP la instalare, emailurile se scriu doar în jurnal. Datele SMTP se pun
   în `.env` (`MAIL_*`), prin File Manager.
4. Cheia publică pentru actualizări este deja în pachet: instalatorul o scrie singur în `.env`.
5. Creează conturile personalului din *Sistem → Personal*.

## 4. Domeniul principal

Pe domeniul principal cPanel folosește `public_html` și de obicei nu permite schimbarea
directorului. Variante:

1. **Legătură simbolică** (testată pe Apache cu regula cPanel `SymLinksIfOwnerMatch`: instalare
   completă, admin, `.env` și `storage` inaccesibile din browser, fișierele de validare SSL servite).
   `public_html` se redenumește (rămâne ca rezervă) și se înlocuiește cu o legătură către `petmarket/public`.
   Din cPanel → *Terminal*:

   ```bash
   cd ~ && mv public_html public_html_vechi && ln -s petmarket/public public_html
   ```

   Revenire: `cd ~ && rm public_html && mv public_html_vechi public_html`.

   **Înainte:** în cPanel → *Domains* se verifică dacă vreun alt domeniu are directorul **în**
   `public_html` (de exemplu `public_html/alt-domeniu.ro`). Dacă da, redenumirea l-ar opri; în acest
   caz se folosește varianta 2.
2. **Furnizorul schimbă document root** către `petmarket/public` (cerere la suport).

Copierea conținutului din `public/` în `public_html` **nu este suportată**: actualizările scriu în
`petmarket/public` și site-ul ar rămâne cu fișiere vechi.

Dacă pe domeniu rulează încă site-ul WordPress folosit de clienți, comutarea se face abia după import,
verificări și aprobare (`docs/IMPORT_RUNBOOK.md`).

## 5. Probleme frecvente

| Simptom | Cauză probabilă | Rezolvare |
|---|---|---|
| Pagină albă sau eroare 500 imediat după dezarhivare | PHP mai vechi de 8.3 | Schimbă versiunea PHP pentru domeniu |
| „Drept de scriere: storage” | permisiuni | 755 pe directoare, 644 pe fișiere, proprietar = utilizatorul contului |
| Se vede lista de fișiere sau `.env` se descarcă | document root greșit | Document root trebuie să fie `petmarket/public` |
| Adminul apare fără stiluri | `public/js`, `public/css` lipsă | Dezarhivează din nou pachetul complet |
| Emailurile nu pleacă | SMTP necompletat sau cron lipsă | Vezi 3.5 și 3.6 |
| Pachetul de actualizare nu se încarcă | limita `upload_max_filesize` | Vezi `UPDATES.md`, „Pachet urcat prin File Manager” |

## 6. Dezinstalare

Șterge subdomeniul, directorul `petmarket/` și baza de date. WordPress nu este afectat de nimic din acest ghid.

## 7. Importul catalogului din magazinul WooCommerce

Pachetul conține exportul catalogului actual (`database/seeders/data/catalog/`, extras pe 23.11.2026).
Din admin, *Sistem → Import catalog*:

1. **Categorii, branduri, pagini**: 173 de categorii pe niveluri, brandurile, paginile legale
   (Termeni, Livrare, Retur, Confidențialitate, Despre noi) și redirecturile adreselor vechi.
2. **Produse**: pe loturi de câte 300; pagina rămâne deschisă până la 100%.
3. **Imagini**: se copiază de pe pet-market.ro în `public/uploads/products`, optimizate WebP. Până la
   copiere, site-ul nou le afișează de pe adresa veche. **Copierea trebuie terminată înainte de oprirea
   WordPress.**

   **Varianta rapidă (recomandată):** dacă WordPress este pe același cont cPanel, pune în `.env`
   `PETMARKET_LEGACY_UPLOADS_PATH="/home/UTILIZATOR/public_html/wp-content/uploads"`: imaginile se copiază
   direct de pe disc, în câteva minute. Altfel se descarcă de pe pet-market.ro, cu pauze, pentru că
   găzduirea actuală are protecție anti-bot și oprește descărcările rapide (copierea reia singură de unde
   a rămas). Fără browser deschis, se poate adăuga temporar o sarcină cron la 5 minute:

```bash
cd /home/UTILIZATOR/petmarket && /usr/local/bin/php artisan petmarket:import --image-batch=200 >> /dev/null 2>&1
```

Importul se poate repeta: actualizează prețurile și stocurile după ID-ul WooCommerce, fără dubluri și
fără să atingă comenzile. Pentru un export proaspăt al catalogului (prețuri și stocuri la zi), vezi
`docs/IMPORT.md`.

## 8. Comutarea domeniului pet-market.ro

1. Instalează și verifică totul pe subdomeniul de probă (comandă de test, emailuri, admin).
2. Rulează din nou importul (prețuri și stocuri la zi) și termină copierea imaginilor.
3. Comută domeniul (secțiunea 4). Adresele vechi rămân valabile: `/produs/…`, `/categorie-produs/…`,
   `/brand/…`, `/cos/`, `/finalizare/`, `/contul-meu/`; cele care s-au schimbat au redirect 301.
4. În Google Search Console trimite noul sitemap (`/sitemap.xml`), iar în Merchant Center feed-ul
   `/feed/google-merchant.xml`.
