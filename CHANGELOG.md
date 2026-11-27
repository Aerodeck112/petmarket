# Jurnal de modificări

## 0.1.1 — 27.11.2026 (actualizare: produse cu rețetă)

- Medicamentele veterinare cu rețetă (584), produsele de uz exclusiv veterinar (10) și cele vândute doar la sediu (4) nu se mai pot comanda online; motivul apare pe card și pe pagina produsului, cu adresa și telefonul.
- La actualizare, o migrare aplică restricțiile pe produsele deja importate; produsele care au deja o restricție setată din admin rămân neschimbate. Dacă produsele nu sunt încă importate, restricțiile se aplică la import.
- Admin: câmpul și filtrul „Restricție de vânzare”; textele afișate se editează din Setări magazin → Reguli comerciale.
- Include și corecturile din 25–27.11: prima pagină (dale, „Cele mai căutate”, bannere, telefon), încadrări corectate, `.htaccess` pentru instalarea în `public_html`.

## 0.1.0 — 23.11.2026 (prima versiune de probă)

### Magazin
- Catalog cu adresele WooCommerce păstrate: `/produs/…`, `/categorie-produs/…/…/`, `/brand/…`, `/eticheta-produs/…`, `/magazin/`, `/cos/`, `/finalizare/`, `/contul-meu/`.
- Filtre fără reîncărcarea paginii (brand, atribute, preț, stoc, reduceri), sortările WooCommerce, căutare cu sugestii (nume, brand, SKU, EAN).
- Pagina de produs: galerie cu zoom, variante, stoc în timp real, prețul cel mai mic din ultimele 30 de zile la reduceri (Omnibus), specificații, recenzii, produse similare.
- Produsele cu rețetă se afișează cu preț, dar fără coș, cu telefonul magazinului (ca în magazinul vechi).
- Coș lateral, bara „livrare gratuită” cu Hiko, cupoane, finalizare pentru persoane fizice și juridice (CUI, Reg. Com.), cont opțional.
- Reguli preluate: livrare cu curier 23 lei, gratuită de la 1.000 lei, fără TVA pe livrare; ramburs și transfer bancar; TVA 21% implicit, cotă pe produs.
- Cont client: comenzi, „comandă din nou”, adrese, date, favorite. Parolele WordPress vechi sunt acceptate.
- SEO: sitemap index cu imagini, robots.txt, date structurate Product / BreadcrumbList / PetStore, canonical, paginile filtrate cu noindex, feed Google Merchant, redirecturi 301 pentru adresele vechi.
- Cookie-uri de măsurare (GTM, GA4, Google Ads, Meta) doar după accept; evenimente GA4 ecommerce.

- Prețurile afișate sunt prețuri de vânzare; TVA-ul se aplică la factură, în programul de facturare.
- Transfer bancar în contul Libra Internet Bank al FEST4PETS SRL; suport online 09:00 – 16:30.

### Ecolet
- Trimiterea comenzii în „Comenzi de trimis” din Ecolet (adresă, localitate din nomenclatorul Ecolet, greutate, ramburs), manual sau automat.
- AWB-ul, curierul și statusul vin înapoi la 10 minute: comanda trece în „Expediată” (clientul primește emailul cu AWB) și în „Finalizată” la livrare.
- AWB-ul în PDF direct din pagina comenzii; test de conexiune din Setări magazin.

### Instalare directă pe pet-market.ro
- Magazinul vechi rămas în `public_html_vechi` este găsit automat: imaginile se copiază de pe disc, iar prețurile, reducerile, stocul exact și starea produselor se citesc din baza WooCommerce (`wp-config.php`).
- Cheie nouă de semnare a actualizărilor, inclusă în pachet.

### Instalare fără Terminal
- Pentru găzduirile la care domeniul servește fix `public_html`: pachetele se dezarhivează în `public_html`, iar fișierul `deploy/public_html.htaccess` trimite toate adresele în `petmarket/public` (testat pe Apache 2.4 + PHP 8.3, instalare completă).

### Produse care nu se vând online
- Toate produsele scanate după statutul legal: online se vând doar medicamentele veterinare fără rețetă (Legea 160/1998, art. 37; Regulamentul (UE) 2019/6). 598 de produse nu se mai pot pune în coș (pe site-ul vechi erau marcate doar 78): antibiotice, vaccinuri, hormoni, antiinflamatoare, injectabile, medicamente pentru animale de fermă și cai, antiparazitarele cu rețetă (Bravecto, NexGard, Simparica, Credelio, Stronghold, Advocate...).
- Motivul se vede pe site: „Cu rețetă” (se ridică de la sediu, cu rețeta), „Uz veterinar” (anestezice, sedative, eutanasiante) sau „Doar la sediu”; textele se editează din Setări magazin → Reguli comerciale.
- În admin: câmpul „Restricție de vânzare” pe fiecare produs și filtrul cu același nume în lista de produse.

### Instalare fără site-ul vechi
- Exportul CSV din WooCommerce (25.11.2026) e inclus: prețuri, promoții, stoc exact, greutate (pentru Ecolet), EAN, TVA redus (118 produse), produsele care nu se vând online (78) și cele 99 de ciorne.
- Pachet separat cu imaginile produselor, gata optimizate; se leagă automat la import. WordPress și baza lui nu mai sunt necesare.

### Catalog curățat, pe structura veche
- Se păstrează categoriile și adresele de pe pet-market.ro (Hrană, Diete, Suplimente, Accesorii, Igienă, Echipamente veterinare); categoriile veterinare razlețe sunt strânse în „Farmacie veterinară”.
- Dublurile unite, „Fără categorie” eliminat: toate cele 5.724 de produse încadrate (137 de categorii), cu animalul (filtrul „Animal”) și brandul (161 de branduri unificate).
- Redirect 301 pentru adresele categoriilor unite și ale brandurilor dublate; redirecturile răspund și la cererile HEAD.
- Paginarea veche `/categorie-produs/…/page/N/` duce la pagina corespunzătoare.

### Administrare
- Panou cu vânzări azi / 7 / 30 de zile, grafic, comenzi recente, stoc redus.
- Comenzi: tab-uri pe status, filtre, export CSV, pagina comenzii cu schimbare de status (cu restituirea stocului), AWB și link de urmărire, încasare, note interne sau către client, proformă și listă de picking.
- Produse: formular pe tab-uri, variante, galerie, atribute, upsell / cross-sell, editarea stocului direct din listă, reduceri în masă, duplicare, export.
- Categorii, branduri, atribute și filtre, cupoane cu toate restricțiile WooCommerce, clienți, recenzii, metode de livrare, bannere, abonați newsletter, registru de stoc.
- Import catalog din browser, pe loturi, și copierea imaginilor pe server.

### Platformă (preluată din HiKeVet)
- Instalator web pentru cPanel cu cod de acces, actualizări ZIP semnate Ed25519 cu backup și revenire, backup zilnic criptat, roluri și permisiuni, 2FA, jurnal de audit.
- Design: paleta muralului HiKeVet, Hiko (ciobănescul) și Raza (pisica cu ochelari) pe tot site-ul, logo-ul și imaginile din magazinul actual.
