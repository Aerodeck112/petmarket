<?php

namespace App\Services\Shop;

/** Judetele Romaniei, in ordinea folosita de WooCommerce (codurile ISO 3166-2:RO). */
final class Counties
{
    public const ALL = [
        'AB' => 'Alba', 'AR' => 'Arad', 'AG' => 'Argeș', 'BC' => 'Bacău', 'BH' => 'Bihor', 'BN' => 'Bistrița-Năsăud', 'BT' => 'Botoșani',
        'BR' => 'Brăila', 'BV' => 'Brașov', 'B' => 'București', 'BZ' => 'Buzău', 'CL' => 'Călărași', 'CS' => 'Caraș-Severin', 'CJ' => 'Cluj',
        'CT' => 'Constanța', 'CV' => 'Covasna', 'DB' => 'Dâmbovița', 'DJ' => 'Dolj', 'GL' => 'Galați', 'GR' => 'Giurgiu', 'GJ' => 'Gorj',
        'HR' => 'Harghita', 'HD' => 'Hunedoara', 'IL' => 'Ialomița', 'IS' => 'Iași', 'IF' => 'Ilfov', 'MM' => 'Maramureș', 'MH' => 'Mehedinți',
        'MS' => 'Mureș', 'NT' => 'Neamț', 'OT' => 'Olt', 'PH' => 'Prahova', 'SJ' => 'Sălaj', 'SM' => 'Satu Mare', 'SB' => 'Sibiu',
        'SV' => 'Suceava', 'TR' => 'Teleorman', 'TM' => 'Timiș', 'TL' => 'Tulcea', 'VL' => 'Vâlcea', 'VS' => 'Vaslui', 'VN' => 'Vrancea',
    ];

    public static function name(?string $code): ?string
    {
        return $code === null ? null : (self::ALL[$code] ?? $code);
    }
}
