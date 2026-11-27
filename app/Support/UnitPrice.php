<?php

namespace App\Support;

use App\Models\Product;

/**
 * Prețul pe unitatea de măsură (lei/kg, lei/l), obligatoriu pentru produsele vândute la greutate sau volum
 * (Directiva 98/6/CE privind indicarea prețurilor, transpusă prin OG nr. 21/1992 și reglementările ANPC).
 * Cantitatea netă se deduce din denumire („Josera 15 kg”, „12 x 85 g”, „100 g – 4656 x 24 buc”) și se poate corecta din admin.
 */
class UnitPrice
{
    /** Categoriile la care produsele se vând la greutate sau volum. */
    public const ELIGIBLE = ['hrana', 'diete-2', 'accesorii/nisip-litiere', 'igiena', 'otc/vitamine-suplimente', 'otc/afectiuni-'];

    public static function eligiblePaths(array $paths): bool
    {
        foreach ($paths as $path) {
            foreach (self::ELIGIBLE as $prefix) {
                if ($path === rtrim($prefix, '/-') || str_starts_with($path, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array{0: float, 1: string}|null cantitatea în kg sau l și unitatea ('kg' / 'l') */
    public static function detect(string $name): ?array
    {
        $n = mb_strtolower(html_entity_decode($name));
        $n = str_replace(['×', ','], ['x', '.'], $n);
        $size = '(\d+(?:\.\d+)?)\s*(kg|gr|g|ml|l)\b';
        $count = 1;
        // „12 x 85 g”, „6x400g”
        if (preg_match('/(?<![\d.\-])(\d{1,3})\s*x\s*'.$size.'/u', $n, $m)) {
            [$count, $value, $unit] = [(int) $m[1], (float) $m[2], $m[3]];
        } elseif (preg_match('/(?<![\d.\-–])'.$size.'/u', $n, $m)) {
            [$value, $unit] = [(float) $m[1], $m[2]];
            // „100 g – 4656 x 24 buc”, „85 g x 12”
            if (preg_match('/x\s*(\d{1,3})\s*(buc|bucati|plicuri|conserve|pliculete)?\s*$/u', $n, $c) || preg_match('/'.preg_quote($m[0], '/').'\s*x\s*(\d{1,3})\b/u', $n, $c)) {
                $count = (int) $c[1];
            }
        } else {
            return null;
        }
        if ($value <= 0 || $count <= 0) {
            return null;
        }
        $qty = match ($unit) {
            'kg', 'l' => $value,
            default => $value / 1000,
        } * $count;

        return [round($qty, 3), in_array($unit, ['ml', 'l'], true) ? 'l' : 'kg'];
    }

    /** Textul afișat lângă preț: „12,34 lei/kg”, sau null. */
    public static function label(Product $product): ?string
    {
        if (! $product->net_quantity || ! $product->net_unit || ! $product->price) {
            return null;
        }

        return money((int) round($product->price / (float) $product->net_quantity)).'/'.$product->net_unit;
    }
}
