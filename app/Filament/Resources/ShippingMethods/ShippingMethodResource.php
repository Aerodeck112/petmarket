<?php

namespace App\Filament\Resources\ShippingMethods;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Filament\Support\Fields;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;
use App\Models\ShippingMethod;
use App\Services\Shop\Counties;

class ShippingMethodResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = ShippingMethod::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Vânzări';

    protected static ?string $navigationLabel = 'Metode de livrare';

    protected static ?string $modelLabel = 'metodă de livrare';

    protected static ?string $pluralModelLabel = 'metode de livrare';

    protected static ?int $navigationSort = 5;

    public static function permission(): string
    {
        return Permission::SHIPPING_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('name')->label('Nume afișat')->required()->columnSpan(2),
            TextInput::make('code')->label('Cod intern')->required()->unique(ignoreRecord: true),
            Select::make('kind')->label('Tip')->options(ShippingMethod::KINDS)->required(),
            Select::make('carrier')->label('Curier')->options(ShippingMethod::CARRIERS)->placeholder('—'),
            TextInput::make('eta')->label('Termen afișat')->placeholder('2–4 zile lucrătoare'),
            Textarea::make('description')->label('Descriere la finalizarea comenzii')->rows(2)->columnSpanFull(),
            Fields::money('cost', 'Cost')->required(),
            Fields::money('free_over', 'Gratuit de la')->helperText('Gol = pragul general din Setări magazin; 0 = niciodată gratuit.'),
            Fields::money('cost_per_kg', 'Cost suplimentar / kg peste 1 kg'),
            TextInput::make('max_weight_grams')->label('Greutate maximă')->numeric()->suffix('g'),
            Select::make('counties')->label('Doar în județele')->multiple()->options(Counties::ALL)->searchable()->helperText('Gol = toată țara.')->columnSpan(2),
            Toggle::make('cod_allowed')->label('Permite plata la livrare')->default(true),
            Toggle::make('is_active')->label('Activă')->default(true),
            TextInput::make('sort')->label('Ordine')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->reorderable('sort')->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Metodă')->weight('medium')->description(fn (ShippingMethod $m) => ShippingMethod::KINDS[$m->kind] ?? $m->kind),
                TextColumn::make('cost')->label('Cost')->formatStateUsing(fn ($state) => $state ? money($state) : 'gratuit'),
                TextColumn::make('free_over')->label('Gratuit de la')->formatStateUsing(fn ($state) => $state === 0 ? 'niciodată' : money($state))->placeholder('pragul general'),
                ToggleColumn::make('cod_allowed')->label('Ramburs'),
                ToggleColumn::make('is_active')->label('Activă'),
            ])
            ->recordActions([EditAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListShippingMethods::route('/'), 'create' => Pages\CreateShippingMethod::route('/create'), 'edit' => Pages\EditShippingMethod::route('/{record}/edit')];
    }
}
