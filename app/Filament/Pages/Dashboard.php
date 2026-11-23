<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Panou magazin';

    protected static ?string $navigationLabel = 'Panou';

    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 3];
    }

    public function getSubheading(): ?string
    {
        $h = (int) now()->format('G');

        return ($h < 11 ? 'Bună dimineața' : ($h < 18 ? 'Bună ziua' : 'Bună seara')).', '.(auth()->user()?->first_name ?: auth()->user()?->name).'! Hiko a adus vânzările, Raza a numărat stocul.';
    }
}
