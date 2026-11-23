# Securitate și GDPR

Ce face platforma, ce rămâne în sarcina magazinului și ce limite are. Nu este un audit extern.

## Măsuri tehnice

| Zonă | Ce face platforma |
|---|---|
| Autentificare personal | `/admin`, parole de minimum 10 caractere, autentificare în doi pași (aplicație TOTP + coduri de recuperare) disponibilă din profil |
| Roluri | Super Admin, Administrator, Manager magazin, Depozit/livrări, Marketing/Editor, Client; fiecare ecran din admin verifică permisiunea la accesarea adresei, nu doar la afișarea butonului |
| Limitare încercări | autentificare 6/minut pe email și IP; formulare 5/minut; finalizare comandă 6/minut; căutare 90/minut |
| Prețuri | coșul păstrează doar ID-uri și cantități; prețul, reducerea, livrarea și TVA-ul se calculează numai pe server, la fiecare afișare și din nou la plasarea comenzii |
| Stoc | produsele se blochează în baza de date la plasare: două comenzi simultane nu pot vinde ultima bucată de două ori |
| Comenzi | pagina de confirmare are o adresă cu token aleatoriu de 48 de caractere; contul clientului vede doar propriile comenzi |
| Jurnal | schimbările de status, AWB, plăți, setări și produse se scriu în jurnalul de audit (cine, când, ce) |
| Antete HTTP | CSP, X-Frame-Options, Referrer-Policy, HSTS în producție |
| HTML din admin | descrierile și paginile trec prin curățare (fără scripturi, iframe-uri, atribute de eveniment) |
| Fișiere | `.env`, `storage/`, `vendor/` sunt în afara directorului public; încărcările acceptă doar imagini |
| Actualizări | doar pachete ZIP semnate Ed25519, cu backup și revenire automată (vezi `UPDATES.md`) |
| Backup | zilnic, criptat XChaCha20-Poly1305 cu cheia derivată din `APP_KEY` |

## Date personale (GDPR)

| Subiect | Situație |
|---|---|
| Date colectate la comandă | nume, email, telefon, adresă de livrare și facturare, firmă și CUI pentru persoane juridice, mențiunea comenzii, IP și browser (prevenirea fraudei) |
| Acorduri | termenii la fiecare comandă (salvat cu data), newsletter opțional și separat, cookie-uri de măsurare doar după accept (GTM, GA4, Google Ads, Meta) |
| Dezabonare | link unic în fiecare email de newsletter |
| Conturi WooCommerce | dacă se importă clienții, parolele WordPress sunt verificate o singură dată și trecute pe algoritmul nou la prima autentificare |
| Termene de păstrare | **de stabilit de magazin** (comenzi: obligațiile fiscale; coșuri abandonate: 60 de zile, șterse automat) |
| Plata cu cardul | datele cardului nu ajung pe server (pagina procesatorului); integrarea se activează după alegerea procesatorului |

## În sarcina magazinului

1. Autentificarea în doi pași pentru tot personalul (nu e impusă automat).
2. Copii ale backupurilor și ale fișierului `.env` în alt loc decât serverul.
3. Textele legale actualizate (termeni, confidențialitate, cookie-uri, retur) în *Conținut → Pagini*.
4. Cheia privată de semnare a actualizărilor păstrată offline.
