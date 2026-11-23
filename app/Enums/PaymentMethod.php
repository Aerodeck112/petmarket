<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case Card = 'card';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Plată la livrare (ramburs)',
            self::Card => 'Card online',
            self::Transfer => 'Transfer bancar (OP)',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Cod => 'Ramburs',
            self::Card => 'Card',
            self::Transfer => 'OP',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
