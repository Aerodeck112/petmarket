# Instalare pe cPanel

Ghidul descrie instalarea Pet Market dintr-un pachet ZIP, fără linie de comandă.

## Pe scurt: instalarea directă pe pet-market.ro

Varianta aleasă: noul magazin ia locul celui vechi pe același cont cPanel. Site-ul este indisponibil
aproximativ 20–30 de minute. WordPress nu se șterge: rămâne în `public_html_vechi`, de unde noul magazin
copiază imaginile și ia prețurile și stocul la zi; revenirea durează un minut.

1. **Backup complet** din cPanel (JetBackup sau *Backup Wizard*): fișiere și baza WordPress.
2. **Bază nouă**: cPanel → *MySQL Databases* → bază goală + utilizator cu *All Privileges* (§3.2).
3. **Pachetul**: în `/home/UTILIZATOR` încarci `petmarket-install-0.1.0.zip` și îl dezarhivezi → `petmarket/`.
4. **Comutarea** (cPanel → *Terminal*), după ce verifici că niciun alt domeniu nu stă în `public_html` (§4):

   ```bash
   cd ~ && mv public_html public_html_vechi && ln -s petmarket/public public_html
   ```

5. **Instalatorul**: deschizi `https://pet-market.ro/install`, codul e în `petmarket/storage/app/install-code.txt` (§3.4).
   La SMTP pui datele căsuței `office@pet-market.ro`.
6. **Catalogul**: `/admin` → *Sistem → Import catalog*: pașii 1 și 2 (categorii, produse), apoi pasul 4
   „Prețuri și stoc din WooCommerce” (citește baza veche, găsită automat în `public_html_vechi/wp-config.php`)
   și pasul 3 „Copiază imaginile” (de pe disc, câteva minute).
7. **Setări**: *Sistem → Setări magazin* → tabul *Ecolet* (Client ID, Client Secret, email și parolă Ecolet),
   apoi „Testează Ecolet”. IBAN-ul și datele firmei sunt deja completate.
8. **Cron** la fiecare minut (§3.5) și o comandă de test cap-coadă (plasare, email, trimitere la Ecolet).
9. **Google**: în Search Console trimiți `https://pet-market.ro/sitemap.xml`.

Revenire la WordPress, oricând: `cd ~ && rm public_html && mv public_html_vechi public_html`.

### Puțin spațiu pe găzduire

Noul magazin ocupă aproximativ 160 MB (plus 44 MB arhiva ZIP, care se șterge după dezarhivare) și o bază de
date de câteva zeci de MB. Din magazinul vechi noul magazin are nevoie doar de trei lucruri:
`wp-config.php`, directorul `wp-content/uploads` (imaginile) și baza de date WordPress. Restul (nucleul
WordPress, pluginuri, teme, cache, backupuri vechi) se poate șterge, după ce există backup pe calculator.
Fără aceste fișiere, revenirea la WordPress înseamnă reinstalarea lui din backup, nu un minut.

1. **Backup pe calculator**: `wp-content/uploads`, `wp-config.php` și baza WordPress (cPanel → *Backup* →
   *Download a MySQL Database Backup*). Un backup lăsat în contul cPanel ocupă spațiu: se descarcă și se șterge.
2. **Eliberare spațiu**: `public_html` se redenumește în `public_html_vechi` (File Manager → *Rename*); în el se
   șterge tot, **în afară de** `wp-config.php` și `wp-content/uploads`. Se mai pot șterge arhivele `.zip` /
   `.tar.gz` vechi din `/home/UTILIZATOR` și fișierele `error_log`. Baza de date WordPress **nu** se șterge.
3. Instalarea continuă cu pașii 2, 3 și 5–9 de mai sus; la pasul 4 rămâne doar legătura
   (`cd ~ && ln -s petmarket/public public_html`), pentru că redenumirea s-a făcut deja.
4. **Imaginile se leagă, nu se copiază**: la pasul 6 se sare „Copiază imaginile”; în cPanel → *Terminal*:

   ```bash
   mkdir -p ~/petmarket/public/wp-content && ln -s ~/public_html_vechi/wp-content/uploads ~/petmarket/public/wp-content/uploads
   ```

   Produsele afișează imaginile de la adresele vechi (`/wp-content/uploads/…`), care continuă să
   funcționeze, inclusiv în Google Imagini. Din acel director nu se execută niciun script.
   Când se eliberează spațiu, se rulează „Copiază imaginile”; după copiere, legătura și `public_html_vechi`
   nu mai sunt necesare.

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
