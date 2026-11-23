<?php

namespace App\Filament\Resources\Brands;

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
use App\Filament\Support\Fields;
use App\Models\Brand;

class BrandResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Branduri';

    protected static ?string $modelLabel = 'brand';

    protected static ?string $pluralModelLabel = 'branduri';

    protected static ?int $navigationSort = 3;

    public static function permission(): string
    {
        return Permission::PRODUCTS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Fields::title('name', 'Nume'), Fields::slug(),
            FileUpload::make('logo')->label('Logo')->disk('public')->directory('brands')->image()
                ->formatStateUsing(fn ($state) => $state ? preg_replace('#^uploads/#', '', $state) : null)
                ->dehydrateStateUsing(fn ($state) => $state ? 'uploads/'.ltrim(preg_replace('#^uploads/#', '', is_array($state) ? reset($state) : $state), '/') : null),
            Grid::make(1)->schema([Toggle::make('is_featured')->label('Pe prima pagină (cu logo)'), TextInput::make('sort')->label('Ordine')->numeric()->default(0)]),
            Fields::rich('description', 'Descriere'),
            Fields::seo()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('name')->modifyQueryUsing(fn (Builder $query) => $query->withCount('products'))
            ->columns([
                ImageColumn::make('logo')->label('')->state(fn (Brand $b) => $b->logo ? asset($b->logo) : null)->height(32),
                TextColumn::make('name')->label('Brand')->searchable()->sortable()->weight('medium'),
                TextColumn::make('slug')->label('Adresă')->formatStateUsing(fn ($state) => '/brand/'.$state.'/')->color('gray')->toggleable(),
                TextColumn::make('products_count')->label('Produse')->sortable()->alignEnd(),
                ToggleColumn::make('is_featured')->label('Prima pagină'),
            ])
            ->filters([TernaryFilter::make('is_featured')->label('Pe prima pagină')])
            ->recordActions([EditAction::make()->iconButton()])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBrands::route('/'), 'create' => Pages\CreateBrand::route('/create'), 'edit' => Pages\EditBrand::route('/{record}/edit')];
    }
}
