<?php

namespace App\Support;

use App\Models\ShippingMethod;

/**
 * Documentele legale ale magazinului (versiunea VERSION), scrise după legislația română aplicabilă comerțului online:
 * OUG 34/2014 (drepturile consumatorilor, dreptul de retragere, formularul din anexa 1), Legea 365/2002 (comerț electronic),
 * OUG 140/2021 (garanția legală de conformitate), OG 21/1992 (protecția consumatorilor, prețuri), Regulamentul (UE) 2016/679
 * (GDPR), Legea 506/2004 (cookie-uri), Legea 160/1998 și Regulamentul (UE) 2019/6 (medicamente veterinare), Ordinul ANPC
 * privind soluționarea alternativă a litigiilor (SAL). Datele firmei și ale livrării se completează din Setări magazin.
 *
 * Textele sunt un punct de plecare solid, nu consultanță juridică: se recomandă o verificare finală de către un jurist.
 */
class LegalDocuments
{
    public const VERSION = '2026-11-27';

    /** @return array<string, array{title: string, body: string}> */
    public static function all(): array
    {
        $r = self::replacements();
        $docs = [];
        foreach (self::templates() as $slug => [$title, $body]) {
            $docs[$slug] = ['title' => $title, 'body' => strtr($body, $r)];
        }

        return $docs;
    }

    /** @return array<string, string> */
    private static function replacements(): array
    {
        $s = fn (string $k) => e((string) Settings::get($k));
        $courier = rescue(fn () => ShippingMethod::query()->where('is_active', true)->orderBy('sort')->first(), null, false);
        $free = (int) Settings::get('commerce.free_shipping_threshold');

        return [
            ':firma' => $s('shop.legal_name'), ':cui' => $s('shop.cui'), ':reg' => $s('shop.reg_com'), ':adresa' => $s('shop.address'),
            ':cod' => $s('shop.postcode'), ':oras' => $s('shop.city'), ':judet' => $s('shop.county'), ':email' => $s('shop.email'),
            ':telefon' => $s('shop.phone'), ':program' => $s('shop.hours'), ':termen' => e((string) (Settings::get('commerce.delivery_time') ?: '1–4 zile lucrătoare')),
            ':cost_livrare' => $courier ? e(money($courier->cost)) : 'costul afișat la finalizarea comenzii',
            ':prag' => $free > 0 ? e(money($free)) : '',
            ':livrare_gratuita' => $free > 0 ? 'Pentru comenzile cu valoarea produselor de cel puțin '.e(money($free)).', livrarea standard este gratuită.' : '',
            ':site' => e(preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/'))),
            ':versiune' => date('d.m.Y', strtotime(self::VERSION)),
        ];
    }

    /** @return array<string, array{0: string, 1: string}> */
    private static function templates(): array
    {
        $firma = '<p><strong>:firma</strong>, cu sediul în :adresa, :oras, județul :judet, cod poștal :cod, înregistrată la Registrul Comerțului sub nr. :reg, CUI :cui, e-mail <a href="mailto::email">:email</a>, telefon :telefon (:program).</p>';

        return [
            'termeni-conditii' => ['Termeni și condiții', <<<HTML
<p><em>Versiune în vigoare din :versiune.</em></p>
<h2>1. Cine suntem</h2>
<p>Site-ul :site (denumit în continuare „site-ul”) este administrat de:</p>
$firma
<p>Ne poți contacta pentru orice întrebare despre produse, comenzi, livrare, retur sau reclamații la datele de mai sus.</p>

<h2>2. Domeniu de aplicare</h2>
<p>Acești termeni se aplică utilizării site-ului și contractelor la distanță încheiate prin site între :firma (vânzătorul) și cumpărători. Pentru cumpărătorii consumatori se aplică în completare prevederile OUG nr. 34/2014 privind drepturile consumatorilor, ale Legii nr. 365/2002 privind comerțul electronic și ale OUG nr. 140/2021 privind vânzarea de bunuri. Nicio prevedere din acești termeni nu limitează drepturile pe care legea le acordă consumatorilor.</p>

<h2>3. Produsele și prezentarea lor</h2>
<p>Pe site sunt prezentate hrană, diete veterinare, suplimente, accesorii, produse de igienă, echipamente veterinare și medicamente veterinare. Descrierile, imaginile și specificațiile provin de la producători sau furnizori și sunt prezentate cu bună-credință; imaginile au rol de prezentare și pot exista diferențe minore de ambalaj.</p>
<p><strong>Medicamentele veterinare care se eliberează pe bază de rețetă</strong> (și produsele de uz exclusiv al medicului veterinar) sunt afișate doar informativ și <strong>nu se vând online</strong>, conform Legii nr. 160/1998 și Regulamentului (UE) 2019/6. Ele nu pot fi adăugate în coș și pot fi eliberate doar la sediul nostru, în condițiile legii. Online se comercializează numai medicamente veterinare care nu necesită rețetă.</p>

<h2>4. Prețuri</h2>
<p>Prețurile sunt exprimate în lei (RON) și sunt prețuri finale, care includ TVA. Costul livrării nu este inclus în prețul produsului; el este afișat separat în coș și la finalizarea comenzii, înainte de trimiterea acesteia. Pentru produsele vândute la greutate sau volum afișăm și prețul pe unitatea de măsură (lei/kg sau lei/l).</p>
<p>La produsele cu reducere afișăm prețul anterior reducerii, care reprezintă cel mai mic preț practicat în ultimele 30 de zile înainte de reducere.</p>
<p>Ne rezervăm dreptul de a modifica prețurile; modificările nu afectează comenzile deja plasate. Dacă, dintr-o eroare tehnică evidentă, un produs este afișat la un preț vădit greșit, te vom contacta înainte de expediere, iar tu poți alege să menții comanda la prețul corect sau să o anulezi fără costuri.</p>

<h2>5. Cum se încheie contractul</h2>
<ol>
<li>Adaugi produsele dorite în coș; în coș poți modifica oricând cantitățile sau poți elimina produse.</li>
<li>La finalizarea comenzii completezi datele de facturare și livrare, alegi metoda de livrare și de plată și vezi rezumatul comenzii, cu totalul de plată (produse, livrare, eventuale reduceri).</li>
<li>Înainte de trimitere poți verifica și corecta orice dată introdusă, revenind la pașii anteriori.</li>
<li>Bifezi acceptarea acestor termeni și apeși butonul <strong>„Comandă cu obligație de plată”</strong>.</li>
<li>Primești pe e-mail confirmarea înregistrării comenzii. Contractul se consideră încheiat în momentul în care îți confirmăm comanda (prin e-mailul de confirmare sau prin expedierea produselor).</li>
</ol>
<p>Contractul se încheie în limba română. Comanda și acești termeni rămân disponibili în e-mailul de confirmare și, dacă ai cont, în secțiunea „Contul meu”.</p>
<p>Putem refuza sau anula o comandă, cu informarea ta, dacă produsul nu mai este disponibil, dacă datele sunt incomplete sau vădit eronate ori dacă există indicii de fraudă. Dacă ai plătit deja, îți returnăm integral suma în cel mult 14 zile.</p>

<h2>6. Plata</h2>
<p>Poți plăti prin <strong>ramburs</strong> (numerar sau card, la curier, după disponibilitatea curierului) sau prin <strong>transfer bancar</strong> (ordin de plată) în contul afișat la finalizarea comenzii; în acest caz comanda se expediază după încasarea plății. Factura fiscală se emite pentru fiecare comandă și se transmite electronic și/sau împreună cu coletul.</p>

<h2>7. Livrarea</h2>
<p>Livrăm pe teritoriul României, prin curier, la adresa indicată în comandă. Termenul estimat de livrare este de :termen de la confirmarea comenzii; în orice caz, livrarea se face în cel mult 30 de zile, conform legii. Costul livrării standard este de :cost_livrare. :livrare_gratuita Detalii complete: <a href="/livrare/">Informații despre livrare</a>.</p>
<p>Riscul pierderii sau deteriorării produselor trece asupra ta în momentul în care tu (sau o persoană desemnată de tine, alta decât curierul) intri în posesia fizică a coletului. Te rugăm să verifici coletul la primire și să ne semnalezi orice deteriorare.</p>

<h2>8. Dreptul de retragere</h2>
<p>Ca și consumator, ai dreptul să te retragi din contract în termen de <strong>14 zile</strong> de la primirea produselor, fără a invoca vreun motiv, cu excepțiile prevăzute de lege. Condițiile, excepțiile și formularul de retragere sunt descrise în <a href="/politica-de-retur/">Politica de retur</a>; formularul este disponibil și separat, la <a href="/formular-retragere/">Formular de retragere</a>.</p>

<h2>9. Garanția legală de conformitate</h2>
<p>Produsele beneficiază de garanția legală de conformitate prevăzută de OUG nr. 140/2021, timp de 2 ani de la livrare. Pentru produsele cu termen de valabilitate (hrană, suplimente, medicamente), conformitatea se raportează la termenul de valabilitate înscris pe ambalaj. Dacă un produs nu este conform, ai dreptul, după caz, la aducerea lui în conformitate (înlocuire sau reparare), la reducerea prețului sau la încetarea contractului, fără costuri. Unele produse (de exemplu echipamentele) pot avea și o garanție comercială a producătorului, menționată în fișa produsului; aceasta nu afectează garanția legală.</p>

<h2>10. Reclamații</h2>
<p>Pentru orice nemulțumire ne poți scrie la <a href="mailto::email">:email</a> sau ne poți suna la :telefon. Îți răspundem cât mai repede, în cel mult 30 de zile de la primirea reclamației.</p>

<h2>11. Soluționarea litigiilor</h2>
<p>Încercăm să rezolvăm orice neînțelegere pe cale amiabilă. Dacă nu reușim, consumatorii pot apela la procedurile de soluționare alternativă a litigiilor (SAL) ale Autorității Naționale pentru Protecția Consumatorilor, prin platforma <a href="https://reclamatiisal.anpc.ro/" rel="noopener">reclamatiisal.anpc.ro</a>, pot depune o reclamație la ANPC (<a href="https://anpc.ro/" rel="noopener">anpc.ro</a>) sau se pot adresa instanțelor judecătorești competente din România.</p>

<h2>12. Recenzii</h2>
<p>Poți lăsa o recenzie la orice produs. Recenziile sunt verificate de echipa noastră înainte de publicare, pentru a elimina mesajele ofensatoare, publicitare sau care nu se referă la produs; nu eliminăm recenziile pentru că sunt negative. Mențiunea „a cumpărat produsul” apare doar la recenziile lăsate din contul unui client care are o comandă cu acel produs.</p>

<h2>13. Contul de client</h2>
<p>Poți comanda și fără cont. Dacă îți creezi un cont, ești responsabil de păstrarea confidențialității parolei. Îți poți șterge contul oricând, scriindu-ne la <a href="mailto::email">:email</a>.</p>

<h2>14. Date personale și cookie-uri</h2>
<p>Prelucrăm datele tale personale conform <a href="/politica-confidentialitate/">Politicii de confidențialitate</a>. Folosim cookie-uri conform <a href="/politica-cookies/">Politicii de cookies</a>; cookie-urile de statistică și marketing se activează doar cu acordul tău.</p>

<h2>15. Proprietate intelectuală</h2>
<p>Conținutul site-ului (texte, elemente grafice, personajele Hiko și Raza, logo-uri) aparține :firma sau partenerilor săi și nu poate fi folosit fără acord scris. Mărcile produselor aparțin titularilor lor.</p>

<h2>16. Modificarea termenilor</h2>
<p>Putem actualiza acești termeni; versiunea nouă se aplică doar comenzilor plasate după publicarea ei. Comenzilor deja plasate li se aplică versiunea acceptată la momentul comenzii.</p>
HTML],

            'politica-de-retur' => ['Politica de retur', <<<HTML
<p><em>Versiune în vigoare din :versiune.</em></p>
<h2>1. Dreptul de retragere în 14 zile</h2>
<p>Ai dreptul să te retragi din contract, fără a preciza motivul, în termen de <strong>14 zile</strong>. Termenul expiră după 14 zile de la ziua în care tu sau o persoană desemnată de tine (alta decât curierul) intră în posesia fizică a produselor; dacă o comandă se livrează în mai multe colete, termenul curge de la primirea ultimului colet.</p>
<p>Pentru a-ți exercita dreptul de retragere, trebuie să ne informezi printr-o declarație neechivocă (de exemplu un e-mail la <a href="mailto::email">:email</a> sau o scrisoare la adresa de mai jos). Poți folosi <a href="/formular-retragere/">formularul de retragere</a>, dar nu este obligatoriu. Este suficient să trimiți comunicarea înainte de expirarea termenului.</p>
$firma

<h2>2. Trimiterea produselor înapoi</h2>
<p>Trimite produsele sau predă-le nouă fără întârzieri nejustificate și în cel mult 14 zile de la data la care ne-ai comunicat retragerea, la adresa: :adresa, :oras, județul :judet, cod poștal :cod. Costul direct al returnării produselor este suportat de tine, cu excepția cazului în care produsul livrat este greșit sau neconform.</p>
<p>Poți manipula produsele cât este necesar pentru a le stabili natura, caracteristicile și funcționarea (ca într-un magazin). Răspunzi doar pentru diminuarea valorii produselor rezultată dintr-o manipulare care depășește acest scop.</p>

<h2>3. Rambursarea</h2>
<p>Îți rambursăm toate sumele primite, <strong>inclusiv costul livrării inițiale</strong> (cu excepția costurilor suplimentare dacă ai ales altă livrare decât cea standard, cea mai ieftină oferită), fără întârzieri nejustificate și în orice caz în cel mult <strong>14 zile</strong> de la data la care am fost informați despre decizia ta de retragere. Putem amâna rambursarea până la primirea produselor sau până când ne trimiți dovada expedierii lor, dacă aceasta intervine mai devreme.</p>
<p>Rambursarea se face prin aceeași metodă de plată folosită la comandă. Pentru comenzile plătite ramburs, rambursarea se face prin transfer bancar în contul (IBAN) pe care ni-l comunici, fără comisioane pentru tine.</p>

<h2>4. Excepții de la dreptul de retragere</h2>
<p>Conform art. 16 din OUG nr. 34/2014, dreptul de retragere nu se aplică, printre altele, pentru:</p>
<ul>
<li>produsele <strong>sigilate care, din motive de protecție a sănătății sau de igienă, nu mai pot fi returnate după desigilare</strong> – de exemplu hrana, recompensele, suplimentele, medicamentele veterinare fără rețetă și produsele de igienă al căror ambalaj sigilat a fost deschis;</li>
<li>produsele care se pot deteriora sau expira rapid;</li>
<li>produsele confecționate după specificațiile tale sau personalizate (de exemplu medalioane gravate).</li>
</ul>
<p>Produsele din aceste categorii, cu sigiliul intact, pot fi returnate în termenul de 14 zile.</p>

<h2>5. Produse neconforme, deteriorate sau greșite</h2>
<p>Dacă ai primit un produs deteriorat în transport, greșit sau neconform, scrie-ne la <a href="mailto::email">:email</a>, ideal cu fotografii. Îl înlocuim sau îți returnăm banii, iar costul transportului este suportat de noi. Drepturile tale din garanția legală de conformitate (OUG nr. 140/2021) sunt descrise în <a href="/termeni-conditii/">Termeni și condiții</a>.</p>

<h2>6. Formularul de retragere</h2>
<p>Poți folosi modelul de mai jos (sau pagina <a href="/formular-retragere/">Formular de retragere</a>), completat și trimis prin e-mail la <a href="mailto::email">:email</a>:</p>
<blockquote>
<p>Către :firma, :adresa, :oras, județul :judet, cod poștal :cod, e-mail :email:</p>
<p>Prin prezenta vă informez că doresc să mă retrag din contractul referitor la vânzarea următoarelor produse: ……………………</p>
<p>Comandate la data …………… / primite la data ……………</p>
<p>Numărul comenzii: ……………</p>
<p>Numele consumatorului: ……………………</p>
<p>Adresa consumatorului: ……………………</p>
<p>IBAN pentru rambursare (dacă ai plătit ramburs): ……………………</p>
<p>Semnătura consumatorului (doar dacă formularul este trimis pe hârtie): ……………</p>
<p>Data: ……………</p>
</blockquote>
HTML],

            'formular-retragere' => ['Formular de retragere', <<<HTML
<p>Completează și trimite acest formular doar dacă dorești să te retragi din contract. Îl poți trimite prin e-mail la <a href="mailto::email">:email</a> sau prin poștă la adresa de mai jos. Folosirea formularului nu este obligatorie: orice declarație clară, trimisă în termenul de 14 zile, este suficientă. Detalii în <a href="/politica-de-retur/">Politica de retur</a>.</p>
<blockquote>
<p>Către :firma, :adresa, :oras, județul :judet, cod poștal :cod, e-mail :email:</p>
<p>Prin prezenta vă informez că doresc să mă retrag din contractul referitor la vânzarea următoarelor produse: ……………………</p>
<p>Comandate la data …………… / primite la data ……………</p>
<p>Numărul comenzii: ……………</p>
<p>Numele consumatorului: ……………………</p>
<p>Adresa consumatorului: ……………………</p>
<p>IBAN pentru rambursare (dacă ai plătit ramburs): ……………………</p>
<p>Semnătura consumatorului (doar dacă formularul este trimis pe hârtie): ……………</p>
<p>Data: ……………</p>
</blockquote>
<p><small>Model conform anexei nr. 1 partea B la OUG nr. 34/2014 privind drepturile consumatorilor.</small></p>
HTML],

            'livrare' => ['Informații despre livrare', <<<HTML
<p><em>Versiune în vigoare din :versiune.</em></p>
<h2>Unde livrăm</h2>
<p>Livrăm în toată România, prin firme de curierat, la adresa indicată în comandă (și, unde este disponibil, în lockere).</p>
<h2>Cât durează</h2>
<p>Termenul estimat este de <strong>:termen</strong> de la confirmarea comenzii. Pentru plata prin transfer bancar, termenul curge de la încasarea plății. În perioadele aglomerate (sărbători, campanii) pot apărea mici întârzieri; în orice caz, livrarea se face în cel mult 30 de zile, conform legii. Când coletul pleacă, primești pe e-mail numărul de urmărire (AWB).</p>
<h2>Cât costă</h2>
<p>Livrarea standard prin curier costă <strong>:cost_livrare</strong>. :livrare_gratuita Costul exact pentru comanda ta este afișat în coș și la finalizarea comenzii, înainte de trimiterea ei.</p>
<h2>Plata</h2>
<p>Poți plăti ramburs, la curier, sau prin transfer bancar. Factura se emite pentru fiecare comandă.</p>
<h2>La primirea coletului</h2>
<p>Te rugăm să verifici coletul în prezența curierului. Dacă ambalajul este deteriorat, menționează acest lucru curierului și scrie-ne la <a href="mailto::email">:email</a>, cu fotografii; rezolvăm noi problema cu firma de curierat.</p>
<h2>Produse care nu se livrează</h2>
<p>Medicamentele veterinare cu rețetă și produsele de uz exclusiv veterinar nu se vând online și nu se livrează prin curier; ele pot fi eliberate doar la sediul nostru, în condițiile legii.</p>
<h2>Contact</h2>
$firma
HTML],

            'politica-confidentialitate' => ['Politica de confidențialitate', <<<HTML
<p><em>Versiune în vigoare din :versiune.</em></p>
<h2>1. Operatorul datelor</h2>
$firma
<p>Pentru orice întrebare despre datele tale personale ne poți scrie la <a href="mailto::email">:email</a>.</p>

<h2>2. Ce date prelucrăm, în ce scop și pe ce temei</h2>
<ul>
<li><strong>Comenzi și livrare</strong>: nume, adresă, telefon, e-mail, produsele comandate, datele de facturare (inclusiv, pentru firme, denumire și CUI). Temei: executarea contractului (art. 6 alin. (1) lit. b GDPR) și obligațiile legale fiscale și contabile (art. 6 alin. (1) lit. c).</li>
<li><strong>Contul de client</strong> (opțional): datele de mai sus, parola (stocată criptat), istoricul comenzilor, produsele favorite. Temei: contractul și consimțământul tău la crearea contului.</li>
<li><strong>Comunicări despre comandă și reclamații</strong>: mesajele schimbate cu noi. Temei: contractul și obligația legală de a soluționa reclamațiile.</li>
<li><strong>Newsletter</strong>: adresa de e-mail. Temei: consimțământul tău (art. 6 alin. (1) lit. a), pe care îl poți retrage oricând, din linkul din fiecare e-mail.</li>
<li><strong>Recenzii</strong>: numele afișat, textul și nota. Temei: consimțământul tău la publicarea recenziei.</li>
<li><strong>Securitatea site-ului</strong>: adresa IP, tipul browserului, jurnale tehnice. Temei: interesul legitim de a proteja site-ul și de a preveni frauda (art. 6 alin. (1) lit. f).</li>
<li><strong>Statistici și publicitate</strong> (Google Analytics, Google Ads, Meta): date despre navigare, prin cookie-uri, numai dacă îți dai acordul. Temei: consimțământul tău (Legea nr. 506/2004 și art. 6 alin. (1) lit. a GDPR).</li>
</ul>

<h2>3. Cui transmitem datele</h2>
<ul>
<li>firmelor de curierat și platformei de expediere prin care predăm coletele (datele de livrare);</li>
<li>furnizorilor de găzduire, e-mail și program de facturare, care prelucrează datele doar în numele și la instrucțiunile noastre;</li>
<li>băncii și, dacă este cazul, procesatorului de plăți;</li>
<li>Google și Meta, numai pentru cookie-urile de statistică și marketing acceptate;</li>
<li>autorităților publice (ANAF, ANPC, instanțe), atunci când legea ne obligă.</li>
</ul>
<p>Nu vindem datele tale. Unii furnizori (de exemplu Google sau Meta) pot transfera date în afara Spațiului Economic European; în aceste cazuri transferul se face pe baza deciziilor de adecvare ale Comisiei Europene (inclusiv EU-US Data Privacy Framework) sau a clauzelor contractuale standard.</p>

<h2>4. Cât timp păstrăm datele</h2>
<ul>
<li>documentele financiar-contabile (facturi, comenzi): termenele prevăzute de Legea contabilității nr. 82/1991 (în prezent 5 ani, respectiv 10 ani pentru registre);</li>
<li>contul de client: până la ștergerea lui la cererea ta; conturile inactive pot fi șterse după 3 ani;</li>
<li>newsletter: până la dezabonare;</li>
<li>mesajele și reclamațiile: până la 3 ani de la soluționare (termenul general de prescripție);</li>
<li>cookie-urile: duratele din <a href="/politica-cookies/">Politica de cookies</a>.</li>
</ul>

<h2>5. Drepturile tale</h2>
<p>Ai dreptul de acces, de rectificare, de ștergere, de restricționare a prelucrării, de portabilitate, de opoziție și dreptul de a-ți retrage oricând consimțământul, fără a afecta prelucrarea făcută înainte de retragere. Pentru exercitarea drepturilor scrie-ne la <a href="mailto::email">:email</a>; îți răspundem în cel mult o lună. Ai și dreptul de a depune o plângere la Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal (ANSPDCP), B-dul G-ral Gheorghe Magheru nr. 28-30, sector 1, București, <a href="https://www.dataprotection.ro/" rel="noopener">www.dataprotection.ro</a>.</p>

<h2>6. Securitate</h2>
<p>Folosim conexiune criptată (HTTPS), parole stocate criptat, acces restricționat la date și copii de siguranță. Nu luăm decizii bazate exclusiv pe prelucrarea automată, cu efecte juridice asupra ta.</p>

<h2>7. Minori</h2>
<p>Site-ul nu se adresează persoanelor sub 16 ani. Comenzile se plasează de persoane majore.</p>

<h2>8. Modificări</h2>
<p>Putem actualiza această politică; versiunea în vigoare și data ei sunt afișate pe această pagină.</p>
HTML],

            'politica-cookies' => ['Politica de cookies', <<<HTML
<p><em>Versiune în vigoare din :versiune.</em></p>
<h2>Ce sunt cookie-urile</h2>
<p>Cookie-urile sunt fișiere mici pe care site-ul le salvează în browserul tău. Unele sunt strict necesare pentru funcționarea magazinului (coș, autentificare, securitate) și nu necesită acordul tău (art. 4 alin. (6) din Legea nr. 506/2004). Celelalte – de statistică și marketing – se activează <strong>numai dacă apeși „Accept”</strong> în mesajul despre cookie-uri.</p>

<h2>Cookie-uri strict necesare</h2>
<table>
<thead><tr><th>Nume</th><th>Rol</th><th>Durată</th></tr></thead>
<tbody>
<tr><td>pet-market-session</td><td>sesiunea ta pe site (coș, autentificare, formulare)</td><td>2 ore de inactivitate</td></tr>
<tr><td>XSRF-TOKEN</td><td>protecție împotriva trimiterii frauduloase de formulare</td><td>2 ore</td></tr>
<tr><td>pm_cart</td><td>păstrează coșul de cumpărături</td><td>30 de zile</td></tr>
<tr><td>pm-consent (stocare locală)</td><td>reține alegerea ta privind cookie-urile</td><td>până o ștergi</td></tr>
</tbody>
</table>

<h2>Cookie-uri de statistică și marketing (doar cu acordul tău)</h2>
<table>
<thead><tr><th>Furnizor</th><th>Nume</th><th>Rol</th><th>Durată</th></tr></thead>
<tbody>
<tr><td>Google Analytics</td><td>_ga, _ga_*</td><td>statistici anonimizate despre vizite</td><td>până la 2 ani</td></tr>
<tr><td>Google Ads</td><td>_gcl_au, _gcl_aw</td><td>măsurarea eficienței reclamelor</td><td>90 de zile</td></tr>
<tr><td>Meta (Facebook)</td><td>_fbp, _fbc</td><td>măsurarea și afișarea reclamelor</td><td>90 de zile</td></tr>
</tbody>
</table>
<p>Lista de mai sus include toate serviciile pe care le putem folosi; unele pot să nu fie active în orice moment.</p>

<h2>Cum îți schimbi alegerea</h2>
<p>Îți poți retrage sau da acordul oricând din linkul <strong>„Setări cookie-uri”</strong> din subsolul site-ului. Poți șterge cookie-urile și din setările browserului; dacă blochezi cookie-urile strict necesare, coșul și contul nu vor funcționa corect.</p>

<h2>Contact</h2>
$firma
HTML],
        ];
    }
}
