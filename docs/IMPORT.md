# Importul catalogului din pet-market.ro (WooCommerce)

## Ce se importă

| Din magazinul vechi | În Pet Market | Observații |
|---|---|---|
| 5.724 produse (toate simple) | Produse | prețuri, reduceri, stoc („N în stoc”), precomenzi, greutate, descrieri, imagini |
| 78 de produse „nu se vând online” | `sold_online = false` | prețul se afișează, cumpărarea e înlocuită cu caseta „Produs cu rețetă” și telefonul |
| 173 de categorii, 4 niveluri | Categorii noi (vezi mai jos) | redirect 301 de la fiecare adresă veche la categoria nouă |
| Branduri (taxonomie + atributul local „Brand”) | Branduri | unificate: 5.667 de produse au brand |
| 179 de etichete | Etichete | adresele `/eticheta-produs/…` rămân valabile |
| Pagini legale și Despre noi | Pagini | `/termeni-conditii/`, `/livrare/`, `/politica-confidentialitate/`, `/politica-de-retur/` (fostul `/refund_returns/`), `/despre-noi/` (fostul `/about-us/`) |
| Meniul principal | ordinea categoriilor | eticheta din meniu devine numele afișat la primul nivel („Suplimente” pentru OTC) |

Nu se importă: comenzile vechi, clienții, cupoanele și recenziile (nu sunt publice). Se pot aduce dintr-un
export al bazei WordPress, când îl primim (parolele clienților se păstrează: la prima autentificare sunt
trecute pe algoritmul nou).

## Reorganizarea catalogului (25.11.2026) – structura veche, curățată

La cererea magazinului se păstrează structura de categorii de pe pet-market.ro (meniul principal), cu
aceleași adrese. Ce era în neregulă se curăță la import:

| Secțiune (adresă) | Ce conține |
|---|---|
| Hrană (`hrana`) | Câini și Pisici › Hrană uscată, Hrană umedă, Recompense, Hrană monoproteică; Rozătoare; Păsări; Pești și reptile |
| Diete (`diete-2`) | Slăbire / diabet, Suport urinar, renal, hepatic, digestiv, dermatologic / hipoalergenic, articular, Convalescență › Câini / Pisici › Umedă / Uscată |
| Suplimente (`otc`) | Antiparazitare (Câini / Pisici – interne, externe, intern/extern), Vitamine și suplimente, Afecțiuni (osteo-articulare, urinare, renale, hepatice, cardiovasculare, stomatologice, oftalmice, dermatologice, digestive), Comportament și stres |
| Accesorii (`accesorii`) | Câini, Pisici, Jucării, Nisip și litiere, Alte animale |
| Igienă (`igiena`) | Câini, Pisici |
| Echipamente veterinare (`echipamente-veterinare`) | subcategoriile din meniul vechi + Instrumentar, Consumabile, Materiale de sutură, Identificare animale |
| Farmacie veterinară (`farmacie-veterinara`) | categoriile veterinare care stăteau separat, în afara meniului: vaccinuri, antibiotice, antiinflamatoare, anestezice, hormonale, mastite, antiparazitare de fermă, vitamine și perfuzii, furaje, medicină internă, dermatologie, biosecuritate, insecticide, animale de fermă |

- „Fără categorie” dispare; toate cele 5.724 de produse au categorie (prima este cea principală).
- Dublurile se unesc: „Accesorii Pet” → Accesorii, „Pet Food Dog / Cat” → Hrană › Câini / Pisici,
  „Diete veterinare”, „Dieta veterinară”, „Dieta umedă / uscată” → Diete, „Custi, internare si contenție” →
  „Cuști / internare / contenție”, „Instrumentar” (1.301 produse în afara meniului) → Echipamente veterinare etc.
- Subcategoriile rămase fără niciun produs nu se mai creează; adresa lor duce la părinte.
- Toate adresele vechi care nu mai există au redirect 301 (77 de categorii, plus variantele scurte).
- Atributul filtrabil **Animal** (Câini, Pisici, Rozătoare, Păsări, Pești, Reptile, Cai, Animale de fermă, Albine).
- Brandurile dublate sunt unificate (Brit Care → Brit, Josera Petfood → Josera, Hill's Pet Nutrition → Hill's,
  ELANCO / Elanco Animal Health → Elanco, Maravet Pet / Vet / Identificare → Maravet etc.), cu redirect 301.

Încadrarea este făcută de `tools/catalog/classify.py` (reguli pe nume, descriere, categoria veche și brand:
întâi animalul și tipul produsului, apoi traducerea în categoriile de mai sus) și scrisă în `database/seeders/data/catalog/classification.json`; arborele și redirecturile în `taxonomy.json`.
Corecturile manuale stau în `tools/catalog/overrides.json` (ID WooCommerce → categorii, animal, brand; cheia
`old_categories` fixează direct căile din arborele de mai sus) și au prioritate. Precizia a fost verificată pe două eșantioane aleatorii (600 + 400 de produse) de un evaluator
independent: 89–93% înainte de ultimele corecturi, peste 97% după; produsele marcate „de verificat” în
fișierul Excel de control sunt cele cu încredere medie.

Reconstruire după modificarea regulilor sau a corecturilor:

```bash
python3 -I tools/catalog/classify.py
```

## Exportul CSV din WooCommerce (25.11.2026)

Exportul din *Produse → Export* (toate coloanele, cu meta) completează exportul public:

```bash
python3 -I tools/catalog/merge_wc_csv.py wc-product-export.csv
```

```bash
python3 -I tools/catalog/classify.py
```

Scrie `wc-export.json` (preț, promoție, stoc exact, comenzi în avans, greutate, EAN, cota de TVA redusă,
marcajul „nu se vinde online” / „necesită rețetă”) și `products-002.json` (produsele nepublicate, importate ca
ciorne). La import, datele din CSV au prioritate față de cele publice; baza WordPress nu mai este necesară.

## Pachetul de imagini

Imaginile produselor (7.700) sunt descărcate și optimizate dinainte (WebP, 1000 px și miniatură 400 px) în
`petmarket-imagini-*.zip`, cu structura `petmarket/public/uploads/products/wp/<calea din wp-content/uploads>.webp`.
Dacă arhivele sunt dezarhivate înainte de import, imaginile devin locale chiar la import; dacă sunt
dezarhivate după, pasul „Copiază imaginile” le leagă fără nicio descărcare.

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
