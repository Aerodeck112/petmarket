<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\Settings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStock extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Stoc redus';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can(Permission::STOCK_MANAGE) || (bool) auth()->user()?->can(Permission::PRODUCTS_MANAGE);
    }

    public function table(Table $table): Table
    {
        return $table->query(Product::query()->published()->where('manage_stock', true)->where('stock_qty', '<=', (int) Settings::get('commerce.low_stock_threshold'))->orderBy('stock_qty'))
            ->paginated([8])->defaultPaginationPageOption(8)
            ->columns([
                TextColumn::make('name')->label('Produs')->limit(42)->tooltip(fn (Product $p) => $p->name),
                TextColumn::make('stock_qty')->label('Stoc')->badge()->color(fn ($state) => $state <= 0 ? 'danger' : 'warning'),
            ])
            ->recordUrl(fn (Product $p) => ProductResource::getUrl('edit', ['record' => $p]))
            ->emptyStateHeading('Totul e pe raft')->emptyStateDescription('Raza a numărat: niciun produs nu e pe terminate.');
    }
}
