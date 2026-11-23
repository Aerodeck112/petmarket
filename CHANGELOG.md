# Jurnal de modificări

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

### Administrare
- Panou cu vânzări azi / 7 / 30 de zile, grafic, comenzi recente, stoc redus.
- Comenzi: tab-uri pe status, filtre, export CSV, pagina comenzii cu schimbare de status (cu restituirea stocului), AWB și link de urmărire, încasare, note interne sau către client, proformă și listă de picking.
- Produse: formular pe tab-uri, variante, galerie, atribute, upsell / cross-sell, editarea stocului direct din listă, reduceri în masă, duplicare, export.
- Categorii, branduri, atribute și filtre, cupoane cu toate restricțiile WooCommerce, clienți, recenzii, metode de livrare, bannere, abonați newsletter, registru de stoc.
- Import catalog din browser, pe loturi, și copierea imaginilor pe server.

### Platformă (preluată din HiKeVet)
- Instalator web pentru cPanel cu cod de acces, actualizări ZIP semnate Ed25519 cu backup și revenire, backup zilnic criptat, roluri și permisiuni, 2FA, jurnal de audit.
- Design: paleta muralului HiKeVet, Hiko (ciobănescul) și Raza (pisica cu ochelari) pe tot site-ul, logo-ul și imaginile din magazinul actual.
