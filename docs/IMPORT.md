# Importul catalogului din pet-market.ro (WooCommerce)

## Ce se importă

| Din magazinul vechi | În Pet Market | Observații |
|---|---|---|
| 5.724 produse (toate simple) | Produse | prețuri, reduceri, stoc („N în stoc”), precomenzi, greutate, descrieri, imagini |
| 78 de produse „nu se vând online” | `sold_online = false` | prețul se afișează, cumpărarea e înlocuită cu caseta „Produs cu rețetă” și telefonul |
| 173 de categorii, 4 niveluri | Categorii | aceeași cale `/categorie-produs/părinte/copil/`; „Fără categorie” ascunsă |
| Branduri (taxonomie + atributul local „Brand”) | Branduri | unificate: 5.667 de produse au brand |
| 179 de etichete | Etichete | adresele `/eticheta-produs/…` rămân valabile |
| Pagini legale și Despre noi | Pagini | `/termeni-conditii/`, `/livrare/`, `/politica-confidentialitate/`, `/politica-de-retur/` (fostul `/refund_returns/`), `/despre-noi/` (fostul `/about-us/`) |
| Meniul principal | ordinea categoriilor | eticheta din meniu devine numele afișat la primul nivel („Suplimente” pentru OTC) |

Nu se importă: comenzile vechi, clienții, cupoanele și recenziile (nu sunt publice). Se pot aduce dintr-un
export al bazei WordPress, când îl primim (parolele clienților se păstrează: la prima autentificare sunt
trecute pe algoritmul nou).

## Ce nu se poate vedea public (de confirmat)

- Cota de TVA pe produs: exportul are prețuri finale; s-au observat 21% și 11%. Implicit se aplică 21%;
  produsele cu 11% se marchează din fișa produsului (*Preț → Cotă TVA*) sau printr-o listă de la contabilitate.
- Data publicării produselor: Store API nu o publică; ordinea „Cele mai noi” urmează ID-ul WooCommerce.
- Stocul „50 în stoc” pare un plafon pus de importul din feed-ul furnizorului (WP All Import).

## Reîmprospătarea exportului

Prețurile și stocurile se schimbă zilnic. Înainte de comutare, exportul se reface și se reimportă:

```bash
rm -rf tools/import/cache && python3 tools/import/fetch_catalog.py
```

```bash
php artisan petmarket:import
```

Importul potrivește produsele după ID-ul WooCommerce: actualizează, nu dublează, și nu atinge comenzile.
Produsele editate între timp în admin sunt suprascrise cu datele din export, deci importul final se face
înainte de a începe lucrul în noul admin.
