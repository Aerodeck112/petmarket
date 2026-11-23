<?php

namespace App\Filament\Resources\Categories;

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
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\Fields;
use App\Models\Category;

/** Categoriile pe niveluri, cu adresa /categorie-produs/parinte/copil/ pastrata din WooCommerce. */
class CategoryResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Categorii';

    protected static ?string $modelLabel = 'categorie';

    protected static ?string $pluralModelLabel = 'categorii';

    protected static ?int $navigationSort = 2;

    public static function permission(): string
    {
        return Permission::PRODUCTS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(2)->schema([
                Fields::title('name', 'Nume'),
                Fields::slug()->helperText('Adresa devine /categorie-produs/{părinte}/{slug}/. Schimbarea mută și subcategoriile: adaugă redirecturi 301.'),
                Select::make('parent_id')->label('Categorie părinte')->options(fn (?Category $record) => collect(ProductResource::categoryOptions())->except($record ? $record->descendantIds() : [])->all())->searchable()->placeholder('— nivel principal —'),
                TextInput::make('sort')->label('Ordine')->numeric()->default(0),
                Fields::rich('description', 'Descriere (afișată în capul paginii)'),
            ]),
            Section::make('Afișare')->columnSpan(1)->schema([
                FileUpload::make('image')->label('Imagine / pictogramă')->disk('public')->directory('categories')->image()
                    ->formatStateUsing(fn ($state) => $state ? preg_replace('#^uploads/#', '', $state) : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? 'uploads/'.ltrim(preg_replace('#^uploads/#', '', is_array($state) ? reset($state) : $state), '/') : null),
                Toggle::make('is_visible')->label('Vizibilă pe site')->default(true),
                Toggle::make('in_menu')->label('Apare în meniu')->default(true),
                Toggle::make('is_featured')->label('Pe prima pagină')->default(false),
            ]),
            Fields::seo()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('path')->paginated([50, 100, 'all'])->defaultPaginationPageOption(100)
            ->columns([
                TextColumn::make('name')->label('Categorie')->searchable()->formatStateUsing(fn ($state, Category $c) => str_repeat('— ', $c->depth).$state)->weight('medium'),
                TextColumn::make('path')->label('Adresă')->formatStateUsing(fn ($state) => '/categorie-produs/'.$state.'/')->color('gray')->size('sm')->searchable()->toggleable(),
                TextColumn::make('product_count')->label('Produse')->numeric()->sortable()->alignEnd(),
                ToggleColumn::make('is_visible')->label('Vizibilă'),
                ToggleColumn::make('in_menu')->label('În meniu'),
                TextColumn::make('sort')->label('Ordine')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TernaryFilter::make('is_visible')->label('Vizibilă'), TernaryFilter::make('in_menu')->label('În meniu')])
            ->recordActions([EditAction::make()->iconButton()])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCategories::route('/'), 'create' => Pages\CreateCategory::route('/create'), 'edit' => Pages\EditCategory::route('/{record}/edit')];
    }
}
