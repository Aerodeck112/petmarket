<?php

namespace App\Enums;

/** Statusurile din WooCommerce, plus „Expediată”, folosit deja de magazin prin AWB. */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case OnHold = 'on_hold';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'În așteptare plată',
            self::Processing => 'În procesare',
            self::OnHold => 'În așteptare',
            self::Shipped => 'Expediată',
            self::Completed => 'Finalizată',
            self::Cancelled => 'Anulată',
            self::Refunded => 'Rambursată',
            self::Failed => 'Eșuată',
        };
    }

    /** Culoarea Filament a etichetei. */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Processing => 'info',
            self::OnHold => 'gray',
            self::Shipped => 'primary',
            self::Completed => 'success',
            self::Cancelled, self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Processing => 'heroicon-o-cog-6-tooth',
            self::OnHold => 'heroicon-o-pause-circle',
            self::Shipped => 'heroicon-o-truck',
            self::Completed => 'heroicon-o-check-badge',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Refunded => 'heroicon-o-arrow-uturn-left',
            self::Failed => 'heroicon-o-exclamation-triangle',
        };
    }

    /** Statusuri in care stocul este rezervat de comanda. */
    public function holdsStock(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Refunded, self::Failed], true);
    }

    /** Statusuri care intra in vanzari (rapoarte, total cheltuit de client). */
    public function countsAsSale(): bool
    {
        return in_array($this, [self::Processing, self::Shipped, self::Completed, self::OnHold], true);
    }

    /** Tranzitiile permise din admin. @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::OnHold, self::Cancelled, self::Failed],
            self::Processing => [self::Shipped, self::Completed, self::OnHold, self::Cancelled, self::Refunded],
            self::OnHold => [self::Processing, self::Shipped, self::Cancelled],
            self::Shipped => [self::Completed, self::Refunded, self::Processing],
            self::Completed => [self::Refunded],
            self::Cancelled => [self::Pending, self::Processing],
            self::Failed => [self::Pending, self::Processing, self::Cancelled],
            self::Refunded => [],
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
