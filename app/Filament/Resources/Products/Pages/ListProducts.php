<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Produs nou')->icon('heroicon-o-plus')];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Toate')->badge(Product::query()->count()),
            'published' => Tab::make('Publicate')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'published')),
            'draft' => Tab::make('Ciorne')->badge(Product::query()->where('status', 'draft')->count() ?: null)->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')),
            'sale' => Tab::make('La reducere')->badge(Product::query()->where('on_sale', true)->count() ?: null)->badgeColor('danger')->modifyQueryUsing(fn (Builder $query) => $query->where('on_sale', true)),
            'out' => Tab::make('Epuizate')->badge(Product::query()->where('stock_status', 'outofstock')->count() ?: null)->badgeColor('warning')->modifyQueryUsing(fn (Builder $query) => $query->where('stock_status', 'outofstock')),
            'rx' => Tab::make('Cu rețetă')->modifyQueryUsing(fn (Builder $query) => $query->where('sold_online', false)),
        ];
    }
}
