<?php

namespace App\Filament\Resources\StockMovements;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use App\Models\StockMovement;

/** Registrul de stoc: fiecare intrare si iesire, cu motivul si autorul. Doar citire. */
class StockMovementResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = StockMovement::class;

    protected static ?string $slug = 'stock';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-up-down';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Registru de stoc';

    protected static ?string $modelLabel = 'mișcare de stoc';

    protected static ?string $pluralModelLabel = 'mișcări de stoc';

    protected static ?int $navigationSort = 6;

    public static function permission(): string
    {
        return Permission::STOCK_MANAGE;
    }

    protected static function writable(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'order', 'user']))
            ->columns([
                TextColumn::make('created_at')->label('Data')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('product.name')->label('Produs')->limit(50)->searchable(),
                TextColumn::make('change')->label('Modificare')->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '').$state)->color(fn ($state) => $state > 0 ? 'success' : 'danger')->weight('bold'),
                TextColumn::make('stock_after')->label('Stoc după'),
                TextColumn::make('reason')->label('Motiv')->badge()->formatStateUsing(fn ($state) => StockMovement::REASONS[$state] ?? $state),
                TextColumn::make('order.number')->label('Comanda')->placeholder('—'),
                TextColumn::make('user.name')->label('Cine')->placeholder('sistem'),
                TextColumn::make('note')->label('Notă')->toggleable()->limit(40),
            ])
            ->filters([SelectFilter::make('reason')->label('Motiv')->options(StockMovement::REASONS)]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStockMovements::route('/')];
    }
}
