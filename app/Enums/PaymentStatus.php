<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Neîncasată',
            self::Paid => 'Încasată',
            self::PartiallyRefunded => 'Rambursată parțial',
            self::Refunded => 'Rambursată',
            self::Failed => 'Eșuată',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Paid => 'success',
            self::PartiallyRefunded, self::Refunded => 'gray',
            self::Failed => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
