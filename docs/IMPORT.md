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

## Reorganizarea catalogului (24.11.2026)

Structura veche (173 de categorii amestecate, 1.176 de produse fără categorie) este înlocuită la import
cu un arbore nou, pe animal și pe nevoie:

| Secțiune | Subcategorii |
|---|---|
| Câini | Hrană uscată, Hrană umedă, Recompense și gustări, Diete veterinare, Suplimente și vitamine, Antiparazitare, Sănătate, Igienă și îngrijire, Accesorii, Jucării |
| Pisici | aceleași, plus Nisip și litiere |
| Alte animale | Rozătoare și iepuri, Păsări, Pești și acvaristică, Reptile și țestoase, Cai, Animale de fermă, Albine |
| Cabinet veterinar | Echipamente medicale, Instrumentar chirurgical, Consumabile medicale, Materiale de sutură, Medicamente veterinare, Vaccinuri, Identificare animale, Biosecuritate și dezinfecție, Dezinsecție și deratizare |

- Fiecare produs are o categorie principală (prima) și, unde e cazul, secundare: un produs pentru câini și
  pisici apare în ambele, un medicament de fermă apare și la Cabinet veterinar.
- Atributul filtrabil **Animal** (Câini, Pisici, Rozătoare, Păsări, Pești, Reptile, Cai, Animale de fermă, Albine).
- Brandurile dublate sunt unificate (Brit Care → Brit, Josera Petfood → Josera, Hill's Pet Nutrition → Hill's,
  ELANCO / Elanco Animal Health → Elanco, Maravet Pet / Vet / Identificare → Maravet etc.); adresele vechi ale
  brandurilor și ale tuturor celor 173 de categorii au redirect 301 către cele noi.

Încadrarea este făcută de `tools/catalog/classify.py` (reguli pe nume, descriere, categoria veche și brand)
și scrisă în `database/seeders/data/catalog/classification.json`; arborele și redirecturile în `taxonomy.json`.
Corecturile manuale stau în `tools/catalog/overrides.json` (ID WooCommerce → categorii, animal, brand) și au
prioritate. Precizia a fost verificată pe două eșantioane aleatorii (600 + 400 de produse) de un evaluator
independent: 89–93% înainte de ultimele corecturi, peste 97% după; produsele marcate „de verificat” în
fișierul Excel de control sunt cele cu încredere medie.

Reconstruire după modificarea regulilor sau a corecturilor:

```bash
python3 tools/catalog/classify.py
```

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
