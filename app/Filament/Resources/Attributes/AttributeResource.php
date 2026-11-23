<?php

namespace App\Filament\Resources\Attributes;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;
use App\Models\Attribute;
use Filament\Forms\Components\ColorPicker;
use Illuminate\Support\Str;

/** Atributele (gramaj, aromă, talie, vârstă…) folosite pentru filtre, specificații și variante. */
class AttributeResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Atribute și filtre';

    protected static ?string $modelLabel = 'atribut';

    protected static ?string $pluralModelLabel = 'atribute';

    protected static ?int $navigationSort = 4;

    public static function permission(): string
    {
        return Permission::PRODUCTS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(4)->components([
            TextInput::make('name')->label('Nume')->required()->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set, callable $get) => blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null)->columnSpan(2),
            TextInput::make('slug')->label('Cod în URL (filter_…)')->required()->unique(ignoreRecord: true)->rule('regex:/^[a-z0-9-]+$/'),
            Select::make('type')->label('Afișare')->options(['select' => 'Listă', 'label' => 'Butoane', 'color' => 'Culori'])->default('select')->required(),
            Toggle::make('is_filterable')->label('Apare în filtrele din categorii')->default(true)->columnSpan(2),
            TextInput::make('sort')->label('Ordine')->numeric()->default(0),
            Repeater::make('values')->label('Valori')->relationship()->orderColumn('sort')->reorderable()->grid(3)->columnSpanFull()
                ->schema([
                    TextInput::make('name')->label('Valoare')->required()->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, callable $get) => blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->label('Cod')->required(),
                    ColorPicker::make('color')->label('Culoare (opțional)'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->modifyQueryUsing(fn (Builder $query) => $query->withCount('values'))
            ->columns([
                TextColumn::make('name')->label('Atribut')->searchable()->weight('medium'),
                TextColumn::make('slug')->label('Parametru')->formatStateUsing(fn ($state) => 'filter_'.$state)->fontFamily('mono')->color('gray'),
                TextColumn::make('values_count')->label('Valori')->alignEnd(),
                ToggleColumn::make('is_filterable')->label('Filtru'),
            ])
            ->recordActions([EditAction::make()->iconButton()])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAttributes::route('/'), 'create' => Pages\CreateAttribute::route('/create'), 'edit' => Pages\EditAttribute::route('/{record}/edit')];
    }
}
