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

    /** Descarca un lot; returneaza [reusite, esuate]. */
    public function run(int $limit = 40): array
    {
        $ok = $failed = 0;
        $images = ProductImage::query()->whereNull('path')->whereNull('width')->whereNotNull('source_url')->with('product:id,slug')->orderBy('id')->limit($limit)->get();
        foreach ($images as $image) {
            try {
                $this->download($image);
                $ok++;
            } catch (Throwable $e) {
                // Imaginea ramane pe adresa sursa; latimea 0 o marcheaza ca esuata, ca sa nu blocheze coada.
                $image->update(['width' => 0]);
                $failed++;
                report($e);
            }
        }

        return [$ok, $failed];
    }

    private function download(ProductImage $image): void
    {
        $response = Http::timeout(25)->withUserAgent('PetMarket-Import/1.0')->get($image->source_url);
        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().' la '.$image->source_url);
        }
        $gd = @imagecreatefromstring($response->body());
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
