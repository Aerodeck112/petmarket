<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use SyncsProduct;

    protected static string $resource = ProductResource::class;

    protected function beforeSave(): void
    {
        $this->rememberStock();
    }

    protected function afterSave(): void
    {
        $this->syncProduct();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label('Vezi pe site')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn () => site_url($this->record->path()), shouldOpenInNewTab: true),
            DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make(),
        ];
    }
}
