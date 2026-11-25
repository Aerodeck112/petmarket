<?php

namespace App\Services\Import;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copiaza imaginile produselor din magazinul vechi in public/uploads/products, optimizate WebP:
 * varianta mare (maxim 1000 px) si miniatura de 400 px folosita in liste. Pana la copiere, site-ul
 * afiseaza imaginea de pe adresa veche, deci copierea trebuie terminata inainte de oprirea WordPress.
 */
class ImageDownloader
{
    public const LARGE = 1000;

    public const THUMB = 400;

    public function pending(): int
    {
        return ProductImage::query()->whereNull('path')->whereNull('width')->whereNotNull('source_url')->count();
    }

    /** Imaginile esuate (marcate cu latimea 0) intra din nou in coada. */
    public function retryFailed(): int
    {
        return ProductImage::query()->whereNull('path')->where('width', 0)->update(['width' => null]);
    }

    /**
     * Copiaza un lot; returneaza [reusite, esuate, blocat]. „Blocat” inseamna ca site-ul vechi a raspuns cu
     * pagina anti-bot a gazduirii: lotul se opreste fara sa marcheze imaginile ca esuate, iar urmatorul lot
     * reia de unde a ramas.
     */
    public function run(int $limit = 40, int $pauseMs = 250): array
    {
        $ok = $failed = 0;
        $images = ProductImage::query()->whereNull('path')->whereNull('width')->whereNotNull('source_url')->with('product:id,slug')->orderBy('id')->limit($limit)->get();
        foreach ($images as $image) {
            try {
                $this->download($image);
                $ok++;
                if ($pauseMs && ! $this->localFile($image->source_url)) {
                    usleep($pauseMs * 1000);
                }
            } catch (TemporarilyBlocked) {
                return [$ok, $failed, true];
            } catch (Throwable $e) {
                // Imaginea ramane pe adresa sursa; latimea 0 o marcheaza ca esuata, ca sa nu blocheze coada.
                $image->update(['width' => 0]);
                $failed++;
                report($e);
            }
        }

        return [$ok, $failed, false];
    }

    /**
     * Imaginea din pachetul de imagini pregatit dinainte (petmarket-imagini.zip, dezarhivat in public/uploads/products/wp),
     * deja optimizata WebP: aceeasi cale ca in wp-content/uploads, cu .webp adaugat. Returneaza calea relativa la uploads/.
     */
    public static function packPath(?string $url): ?string
    {
        if (! $url || ! preg_match('#/wp-content/uploads/(.+)$#', (string) parse_url($url, PHP_URL_PATH), $m) || str_contains($m[1], '..')) {
            return null;
        }
        $rel = 'products/wp/'.rawurldecode($m[1]).'.webp';

        return is_file(public_path('uploads/'.$rel)) ? $rel : null;
    }

    /** Leaga imaginile care exista deja in pachet; returneaza cate au fost legate. */
    public function linkPack(): int
    {
        $n = 0;
        ProductImage::query()->whereNull('path')->whereNotNull('source_url')->orderBy('id')->chunkById(500, function ($images) use (&$n) {
            foreach ($images as $image) {
                if ($rel = self::packPath($image->source_url)) {
                    [$w, $h] = @getimagesize(public_path('uploads/'.$rel)) ?: [null, null];
                    $image->update(['path' => $rel, 'width' => $w, 'height' => $h]);
                    $n++;
                }
            }
        });

        return $n;
    }

    /** Fisierul corespunzator din wp-content/uploads, daca directorul vechi este pe acelasi server. */
    private function localFile(string $url): ?string
    {
        $root = LegacyWordPress::uploadsPath();
        if (! $root || ! preg_match('#/wp-content/uploads/(.+)$#', (string) parse_url($url, PHP_URL_PATH), $m)) {
            return null;
        }
        $path = rtrim($root, '/').'/'.rawurldecode($m[1]);

        return is_file($path) && ! str_contains($m[1], '..') ? $path : null;
    }

    private function download(ProductImage $image): void
    {
        if ($rel = self::packPath($image->source_url)) {
            [$w, $h] = @getimagesize(public_path('uploads/'.$rel)) ?: [null, null];
            $image->update(['path' => $rel, 'width' => $w, 'height' => $h]);

            return;
        }
        if ($local = $this->localFile($image->source_url)) {
            $body = (string) file_get_contents($local);
        } else {
            $response = Http::timeout(25)->withUserAgent('Mozilla/5.0 (compatible; PetMarket-Import/1.0)')->accept('image/*')->get($image->source_url);
            if (in_array($response->status(), [403, 429, 503], true) || str_contains((string) $response->header('Content-Type'), 'text/html')) {
                throw new TemporarilyBlocked('Site-ul vechi a cerut o pauză (protecție anti-bot).');
            }
            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status().' la '.$image->source_url);
            }
            $body = $response->body();
        }
        $gd = @imagecreatefromstring($body);
        if (! $gd) {
            throw new \RuntimeException('Imagine invalidă: '.$image->source_url);
        }
        $w = imagesx($gd);
        $h = imagesy($gd);
        $slug = Str::limit($image->product?->slug ?: 'produs', 80, '');
        $rel = 'products/'.date('Y/m').'/'.$slug.'-'.$image->id.'.webp';
        $dir = public_path('uploads/'.dirname($rel));
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        [$lw, $lh] = $this->save($gd, $w, $h, self::LARGE, public_path('uploads/'.$rel));
        $this->save($gd, $w, $h, self::THUMB, public_path('uploads/'.preg_replace('/\.webp$/', '-400.webp', $rel)));
        imagedestroy($gd);
        $image->update(['path' => $rel, 'width' => $lw, 'height' => $lh]);
    }

    /** @return array{0:int,1:int} */
    private function save(\GdImage $src, int $w, int $h, int $max, string $target): array
    {
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $canvas = imagecreatetruecolor($nw, $nh);
        // Fundal alb: multe poze de produs sunt PNG cu transparenta, iar cardurile sunt albe.
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagewebp($canvas, $target, 82);
        imagedestroy($canvas);

        return [$nw, $nh];
    }
}
