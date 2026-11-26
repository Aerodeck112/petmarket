<?php

namespace App\Filament\Resources\Products;

use App\Enums\Permission;
use App\Enums\StockStatus;
use App\Filament\Concerns\GatedByPermission;
use App\Filament\Support\Fields;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Services\Shop\StockService;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/** Produsele: lista cu editare rapida a stocului si formularul pe tab-uri (echivalentul WooCommerce → Produse). */
class ProductResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Product::class;

    protected static ?string $slug = 'products';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Produse';

    protected static ?string $modelLabel = 'produs';

    protected static ?string $pluralModelLabel = 'produse';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function permission(): string
    {
        return Permission::PRODUCTS_MANAGE;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'gtin'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function categoryOptions(): array
    {
        return Category::query()->orderBy('path')->get(['id', 'name', 'path', 'depth'])
            ->mapWithKeys(fn ($c) => [$c->id => str_repeat('— ', $c->depth).$c->name])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make('produs')->persistTabInQueryString()->tabs([
                Tab::make('General')->icon('heroicon-o-pencil-square')->schema([
                    Grid::make(3)->schema([
                        Fields::title('name', 'Nume produs')->columnSpan(2),
                        Select::make('status')->label('Stare')->options(Product::STATUSES)->default('published')->required(),
                    ]),
                    Grid::make(3)->schema([
                        Fields::slug()->helperText('Adresa produsului: /produs/{slug}/. Dacă o schimbi, adaugă un redirect 301 de la cea veche.')->columnSpan(2),
                        Select::make('type')->label('Tip')->options(Product::TYPES)->default('simple')->required()->live(),
                    ]),
                    Grid::make(2)->schema([
                        Select::make('brand_id')->label('Brand')->relationship('brand', 'name')->searchable()->preload()
                            ->createOptionForm([TextInput::make('name')->label('Nume')->required(), TextInput::make('slug')->required()]),
                        Select::make('categories')->label('Categorii')->relationship('categories', 'name')->multiple()->searchable()
                            ->options(fn () => self::categoryOptions())->helperText('Produsul apare și în categoriile părinte.'),
                    ]),
                    Fields::rich('short_description', 'Descriere scurtă (lângă preț)'),
                    Fields::rich('description', 'Descriere completă'),
                ]),
                Tab::make('Preț')->icon('heroicon-o-banknotes')->visible(fn (Get $get) => $get('type') !== 'variable')->schema([
                    Grid::make(2)->schema([
                        Fields::money('regular_price', 'Preț de vânzare')->required(fn (Get $get) => $get('type') !== 'variable'),
                        Fields::money('sale_price', 'Preț redus')->helperText('Gol = fără reducere.'),
                    ]),
                    Grid::make(2)->schema([
                        DateTimePicker::make('sale_starts_at')->label('Reducerea începe')->seconds(false)->native(false),
                        DateTimePicker::make('sale_ends_at')->label('Reducerea se termină')->seconds(false)->native(false),
                    ]),
                    \Filament\Forms\Components\Placeholder::make('omnibus')->label('Cel mai mic preț din ultimele 30 de zile')
                        ->content(fn (?Product $record) => $record && ($l = $record->lowestPrice30()) ? money($l).' (se afișează automat lângă reducere)' : 'fără istoric încă'),
                ]),
                Tab::make('Stoc și livrare')->icon('heroicon-o-archive-box')->schema([
                    Grid::make(3)->schema([
                        TextInput::make('sku')->label('Cod produs (SKU)')->maxLength(100)->unique(ignoreRecord: true),
                        TextInput::make('gtin')->label('Cod de bare (EAN/GTIN)')->maxLength(40),
                        TextInput::make('weight_grams')->label('Greutate colet')->numeric()->suffix('g'),
                    ]),
                    Grid::make(3)->visible(fn (Get $get) => $get('type') !== 'variable')->schema([
                        Toggle::make('manage_stock')->label('Gestionez stocul')->live()->inline(false),
                        TextInput::make('stock_qty')->label('Cantitate în stoc')->numeric()->visible(fn (Get $get) => $get('manage_stock'))
                            ->helperText('Modificările făcute aici apar în registrul de stoc ca ajustări manuale.'),
                        Select::make('stock_status')->label('Disponibilitate')->options(StockStatus::options())->visible(fn (Get $get) => ! $get('manage_stock')),
                    ]),
                    Grid::make(3)->schema([
                        Toggle::make('allow_backorders')->label('Permite precomenzi (stoc 0)')->inline(false),
                        TextInput::make('min_qty')->label('Cantitate minimă')->numeric()->default(1)->minValue(1),
                        TextInput::make('max_qty')->label('Cantitate maximă / comandă')->numeric(),
                    ]),
                ]),
                Tab::make('Imagini')->icon('heroicon-o-photo')->schema([
                    Repeater::make('images')->label('Galerie')->relationship()->orderColumn('sort')->reorderable()->collapsible()->grid(3)
                        ->itemLabel(fn (array $state) => $state['alt'] ?? null)
                        ->schema([
                            FileUpload::make('path')->label('Imagine')->disk('public')->directory('products/manual')->image()->imageEditor()->maxSize(6144)
                                ->helperText('Prima imagine este imaginea principală.'),
                            TextInput::make('source_url')->label('sau adresă externă')->url()->maxLength(700)
                                ->helperText('Folosită până la copierea imaginii pe server.'),
                            TextInput::make('alt')->label('Text alternativ')->maxLength(190),
                        ]),
                ]),
                Tab::make('Variante')->icon('heroicon-o-squares-2x2')->visible(fn (Get $get) => $get('type') === 'variable')->schema([
                    Repeater::make('variants')->label('Variante (gramaj, mărime, aromă…)')->relationship()->orderColumn('sort')->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => trim(($state['label'] ?? '').' '.(isset($state['regular_price']) && $state['regular_price'] !== '' ? '· '.$state['regular_price'].' lei' : '')) ?: 'Variantă nouă')
                        ->columns(4)->schema([
                            TextInput::make('label')->label('Etichetă')->placeholder('2 kg')->maxLength(190),
                            Select::make('values')->label('Valori atribute')->relationship('values', 'name')->multiple()->preload()
                                ->getOptionLabelFromRecordUsing(fn (AttributeValue $v) => $v->attribute->name.': '.$v->name)->columnSpan(2),
                            TextInput::make('sku')->label('SKU')->maxLength(100),
                            Fields::money('regular_price', 'Preț')->required(),
                            Fields::money('sale_price', 'Preț redus'),
                            Toggle::make('manage_stock')->label('Stoc gestionat')->default(true)->inline(false),
                            TextInput::make('stock_qty')->label('Stoc')->numeric(),
                            Select::make('stock_status')->label('Disponibilitate')->options(StockStatus::options())->default('instock'),
                            TextInput::make('weight_grams')->label('Greutate (g)')->numeric(),
                            Toggle::make('is_active')->label('Activă')->default(true)->inline(false),
                        ]),
                ]),
                Tab::make('Atribute')->icon('heroicon-o-adjustments-horizontal')->schema([
                    Select::make('attributeValues')->label('Specificații și filtre')->relationship('attributeValues', 'name')->multiple()->preload()->searchable()
                        ->getOptionLabelFromRecordUsing(fn (AttributeValue $v) => $v->attribute->name.': '.$v->name)
                        ->helperText('Apar în tabul „Specificații” și în filtrele categoriei. Atributele noi se creează din Catalog → Atribute.'),
                    Select::make('tags')->label('Etichete')->relationship('tags', 'name')->multiple()->searchable()
                        ->createOptionForm([TextInput::make('name')->required(), TextInput::make('slug')->required()]),
                    Grid::make(2)->schema([
                        Select::make('upsells')->label('Recomandate (upsell, pe pagina produsului)')->relationship('upsells', 'name')->multiple()->searchable(),
                        Select::make('crossSells')->label('Merg împreună (cross-sell, în coș)')->relationship('crossSells', 'name')->multiple()->searchable(),
                    ]),
                ]),
                Tab::make('Vizibilitate și SEO')->icon('heroicon-o-eye')->schema([
                    Grid::make(4)->schema([
                        Toggle::make('sold_online')->label('Se vinde online')->default(true)->inline(false)
                            ->helperText('Oprit pentru produsele cu rețetă: prețul apare, dar nu se poate comanda.'),
                        Select::make('sale_restriction')->label('Restricție de vânzare')->options(Product::RESTRICTIONS)->placeholder('Fără restricție')
                            ->helperText('Cu restricție, produsul nu se poate pune în coș; pe site apare motivul și telefonul.'),
                        Toggle::make('is_featured')->label('Recomandat pe prima pagină')->inline(false),
                        Toggle::make('is_visible')->label('Vizibil în catalog și căutare')->default(true)->inline(false),
                        TextInput::make('badge')->label('Etichetă proprie')->maxLength(40)->placeholder('Bestseller'),
                    ]),
                    Textarea::make('purchase_note')->label('Notă după cumpărare (în email)')->rows(2),
                    Fields::seo(),
                ]),
            ])->contained(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['mainImage', 'brand', 'categories']))
            ->striped()
            ->columns([
                ImageColumn::make('thumb')->label('')->state(fn (Product $p) => $p->mainImage?->thumbUrl())->square()->size(52)->extraImgAttributes(['loading' => 'lazy'])->extraAttributes(['class' => 'pm-prod-thumb']),
                TextColumn::make('name')->label('Produs')->searchable(query: fn (Builder $query, string $s) => \App\Services\Shop\CatalogQuery::applySearch($query, $s))
                    ->sortable()->wrap()->weight('medium')->limit(70)
                    ->description(fn (Product $p) => collect([$p->sku ? 'SKU '.$p->sku : null, $p->brand?->name])->filter()->implode(' · ')),
                TextColumn::make('categories.name')->label('Categorii')->badge()->color('gray')->limitList(2)->toggleable(),
                TextColumn::make('price')->label('Preț')->sortable()->alignEnd()
                    ->formatStateUsing(fn ($state, Product $p) => $state === null ? '—' : ($p->isVariable() && $p->price_max > $p->price ? money($p->price).' – '.money($p->price_max) : money($state)))
                    ->description(fn (Product $p) => $p->on_sale && ! $p->isVariable() ? 'era '.money($p->regular_price).' (-'.$p->discountPercent().'%)' : null)
                    ->color(fn (Product $p) => $p->on_sale ? 'danger' : null),
                TextInputColumn::make('stock_qty')->label('Stoc')->type('number')->width('6rem')
                    ->disabled(fn (Product $p) => $p->isVariable() || ! $p->manage_stock)
                    ->updateStateUsing(function (Product $record, $state) {
                        app(StockService::class)->set($record, null, (int) $state, 'Modificat din lista de produse');

                        return $record->fresh()->stock_qty;
                    }),
                TextColumn::make('stock_status')->label('Disponibil')->badge()
                    ->formatStateUsing(fn (StockStatus $state) => $state->label())->color(fn (StockStatus $state) => $state->color()),
                ToggleColumn::make('sold_online')->label('Online')->toggleable(),
                TextColumn::make('status')->label('Stare')->badge()->formatStateUsing(fn ($state) => Product::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'published' ? 'success' : 'gray')->toggleable(),
                TextColumn::make('sold_count')->label('Vândute')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Modificat')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('categories')->label('Categorie')->options(fn () => self::categoryOptions())->searchable()
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereIn('id', fn ($s) => $s->select('product_id')->from('category_product')
                        ->whereIn('category_id', Category::query()->find($data['value'])?->descendantIds() ?? [0])) : $query),
                SelectFilter::make('brand_id')->label('Brand')->relationship('brand', 'name')->searchable()->preload(),
                SelectFilter::make('stock_status')->label('Disponibilitate')->options(StockStatus::options()),
                SelectFilter::make('status')->label('Stare')->options(Product::STATUSES),
                TernaryFilter::make('on_sale')->label('La reducere'),
                TernaryFilter::make('sold_online')->label('Se vinde online'),
                \Filament\Tables\Filters\SelectFilter::make('sale_restriction')->label('Restricție de vânzare')->options(Product::RESTRICTIONS),
                Filter::make('low')->label('Stoc redus')->query(fn (Builder $query) => $query->where('manage_stock', true)->where('stock_qty', '<=', (int) Settings::get('commerce.low_stock_threshold'))),
                Filter::make('no_image')->label('Fără imagine')->query(fn (Builder $query) => $query->whereDoesntHave('images')),
                Filter::make('no_category')->label('Fără categorie')->query(fn (Builder $query) => $query->whereDoesntHave('categories')),
                Filter::make('no_sku')->label('Fără SKU')->query(fn (Builder $query) => $query->whereNull('sku')),
                TrashedFilter::make()->label('Șterse'),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                EditAction::make()->iconButton(),
                Action::make('view')->label('Vezi pe site')->icon('heroicon-o-arrow-top-right-on-square')->iconButton()->url(fn (Product $p) => site_url($p->path()), shouldOpenInNewTab: true),
                ReplicateAction::make()->iconButton()->label('Duplică')
                    ->mutateRecordDataUsing(fn (array $data) => array_merge($data, ['name' => $data['name'].' (copie)', 'slug' => $data['slug'].'-copie-'.random_int(100, 999),
                        'sku' => null, 'wc_product_id' => null, 'status' => 'draft', 'sold_count' => 0])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('discount')->label('Aplică reducere %')->icon('heroicon-o-receipt-percent')
                        ->schema([
                            TextInput::make('percent')->label('Reducere')->numeric()->suffix('%')->minValue(1)->maxValue(90)->required(),
                            DateTimePicker::make('ends')->label('Până la (opțional)')->seconds(false)->native(false),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $n = 0;
                            foreach ($records as $p) {
                                if ($p->type === 'simple' && $p->regular_price) {
                                    $p->update(['sale_price' => (int) round($p->regular_price * (100 - (float) $data['percent']) / 100), 'sale_starts_at' => null, 'sale_ends_at' => $data['ends'] ?? null]);
                                    $n++;
                                }
                            }
                            Notification::make()->title("Reducere aplicată la {$n} produse")->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('nodiscount')->label('Scoate reducerea')->icon('heroicon-o-x-mark')->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn (Product $p) => $p->update(['sale_price' => null, 'sale_starts_at' => null, 'sale_ends_at' => null]))),
                    BulkAction::make('publish')->label('Publică')->icon('heroicon-o-eye')->action(fn (Collection $records) => $records->each->update(['status' => 'published'])),
                    BulkAction::make('draft')->label('Trece în ciornă')->icon('heroicon-o-eye-slash')->action(fn (Collection $records) => $records->each->update(['status' => 'draft'])),
                    BulkAction::make('category')->label('Adaugă în categorie')->icon('heroicon-o-folder-plus')
                        ->schema([Select::make('category')->label('Categorie')->options(fn () => self::categoryOptions())->searchable()->required()])
                        ->action(fn (Collection $records, array $data) => $records->each(fn (Product $p) => $p->categories()->syncWithoutDetaching([$data['category']]))),
                    BulkAction::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (Collection $records) => response()->streamDownload(function () use ($records) {
                            $out = fopen('php://output', 'w');
                            fwrite($out, "\xEF\xBB\xBF");
                            fputcsv($out, ['ID', 'SKU', 'Nume', 'Brand', 'Preț', 'Preț redus', 'Stoc', 'Disponibilitate', 'URL'], ';');
                            foreach ($records as $p) {
                                fputcsv($out, [$p->id, $p->sku, $p->name, $p->brand?->name, number_format(($p->regular_price ?? 0) / 100, 2, ',', ''),
                                    $p->sale_price ? number_format($p->sale_price / 100, 2, ',', '') : '', $p->stock_qty, $p->stock_status->label(), site_url($p->path())], ';');
                            }
                            fclose($out);
                        }, 'produse-'.now()->format('Y-m-d').'.csv')),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProducts::route('/'), 'create' => Pages\CreateProduct::route('/create'), 'edit' => Pages\EditProduct::route('/{record}/edit')];
    }
}
