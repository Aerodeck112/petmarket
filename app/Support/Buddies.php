<?php

namespace App\Support;

/**
 * Raza (pisica cu ochelari) si Hiko (ciobanescul), mascotele HiKeVet, pe paginile interioare ale magazinului.
 * Fiecare sectiune are personajul ei si cateva replici; la click personajul spune urmatoarea.
 */
class Buddies
{
    /** Prima potrivire castiga; cheile sunt prefixe de cale, fara slash la inceput. */
    private const BY_PATH = [
        'cos' => ['dog', 'peek', ['Ham! Coșul arată foarte bine.', 'Nu uita recompensele. Pentru mine. Adică pentru câinele tău.', 'Eu sunt Hiko. Duc eu sacoșa!']],
        'finalizare' => ['cat', 'sit', ['Aproape gata. Eu verific adresa cu ochelarii.', 'Datele tale stau la mine în siguranță. Miau.', 'Încă un clic și pleacă coletul!']],
        'comanda' => ['dog', 'peek', ['Ham-ham! Comanda e la noi!', 'Abia aștept să ajungă coletul.', 'Mulțumim! Eu dau din coadă de bucurie.']],
        'contul-meu/autentificare' => ['cat', 'sit', ['Doar cu parola. Sunt Raza, pisica de pază.', 'Nu spun nimănui parola ta. Miau.', 'Te-am recunoscut după pași.']],
        'contul-meu/parola-pierduta' => ['cat', 'sit', ['Se întâmplă. Și eu uit unde am pus jucăria.', 'Îți trimitem un link, tu doar verifici emailul.']],
        'contul-meu/parola-noua' => ['cat', 'sit', ['Alege o parolă bună. Nu „miau123”.', 'Gata, nu ți-o mai uit eu. Miau.']],
        'contul-meu' => ['dog', 'peek', ['Ham! Ce bine că ai venit!', 'Aici îți găsești comenzile și favoritele.', 'Comandă din nou cu un singur clic. Eu aș face-o.']],
        'cautare' => ['cat', 'sit', ['Caut și eu. Cu ochelarii pe nas.', 'Încearcă și după brand sau cod de produs.', 'Am găsit! Cred. Miau?']],
        'branduri' => ['dog', 'peek', ['Le-am mirosit pe toate. Recomand.', 'Eu sunt Hiko. Brandul meu preferat e „mai mult”.']],
        'blog' => ['cat', 'sit', ['Citesc tot. Mai ales partea cu recompensele.', 'Miau. Articol bun, recomand.', 'Eu citesc cu ochelarii pe nas.']],
        'intrebari-frecvente' => ['dog', 'peek', ['Întreabă-mă orice!', 'Știu răspunsul. Cred. Ham?', 'Dacă nu găsești ce cauți, sună-ne.']],
        'contact' => ['dog', 'peek', ['Sună-ne! Eu dau din coadă la telefon.', 'Ne găsești ușor: urmează mirosul de recompense.', 'Eu sunt Hiko. Te aștept la ușă!']],
        'termeni' => ['cat', 'sleep', ['Zzz… documentele mă adorm.', 'Citește tu, eu țin pernuța caldă.', 'Miau? A, încă citești. Bravo.']],
        'politica' => ['cat', 'sleep', ['Zzz… documentele mă adorm.', 'Citește tu, eu țin pernuța caldă.']],
        'livrare' => ['dog', 'peek', ['Curierul vine repede. Îl latru eu.', 'Coletul ajunge de regulă în câteva zile lucrătoare.']],
        'retur' => ['cat', 'sit', ['Se mai întâmplă. Și eu returnez cutia, dar păstrez cutia.', 'Ai 14 zile la dispoziție. Miau.']],
    ];

    /** Pentru paginile fara intrare: alternam intre pisica si caine, stabil pe aceeasi pagina. */
    private const FALLBACK = [
        ['cat', 'sit', ['Miau! Eu sunt Raza. Bine ai venit!', 'Ochelarii sunt de soare, nu de vedere.']],
        ['dog', 'peek', ['Ham! Eu sunt Hiko. Bine ai venit!', 'Mă scarpini? Doar puțin.']],
    ];

    /** @return array{kind: string, pose: string, say: list<string>} */
    public static function forPath(?string $path = null): array
    {
        $path = trim($path ?? request()->path(), '/');
        foreach (self::BY_PATH as $prefix => [$kind, $pose, $say]) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/') || str_starts_with($path, $prefix.'-')) {
                return compact('kind', 'pose', 'say');
            }
        }
        [$kind, $pose, $say] = self::FALLBACK[crc32($path) % 2];

        return compact('kind', 'pose', 'say');
    }
}
