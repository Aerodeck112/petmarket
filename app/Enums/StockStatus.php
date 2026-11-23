<?php

namespace App\Enums;

enum StockStatus: string
{
    case InStock = 'instock';
    case OutOfStock = 'outofstock';
    case OnBackorder = 'onbackorder';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'În stoc',
            self::OutOfStock => 'Stoc epuizat',
            self::OnBackorder => 'La comandă',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::OutOfStock => 'danger',
            self::OnBackorder => 'warning',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
