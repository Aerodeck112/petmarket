<?php

namespace App\Filament\Resources\Coupons;

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
use App\Filament\Resources\Products\ProductResource;
use App\Models\Coupon;
use Illuminate\Support\Str;

/** Cupoanele, cu toate regulile din WooCommerce (perioadă, limite, sumă minimă, restricții). */
class CouponResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Cupoane';

    protected static ?string $modelLabel = 'cupon';

    protected static ?string $pluralModelLabel = 'cupoane';

    protected static ?int $navigationSort = 1;

    public static function permission(): string
    {
        return Permission::COUPONS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Cupon')->columnSpan(2)->columns(2)->schema([
                TextInput::make('code')->label('Cod')->required()->maxLength(60)->unique(ignoreRecord: true)->extraInputAttributes(['style' => 'text-transform:uppercase'])
                    ->suffixAction(Action::make('gen')->icon('heroicon-o-sparkles')->tooltip('Generează')->action(fn (callable $set) => $set('code', 'PM-'.Str::upper(Str::random(6))))),
                TextInput::make('description')->label('Descriere internă')->maxLength(190),
                Select::make('type')->label('Tip reducere')->options(Coupon::TYPES)->default('percent')->required()->live(),
                TextInput::make('percent')->label('Procent')->numeric()->suffix('%')->minValue(0.01)->maxValue(100)->visible(fn (Get $get) => $get('type') === 'percent')->required(fn (Get $get) => $get('type') === 'percent'),
                Fields::money('amount', 'Sumă')->visible(fn (Get $get) => $get('type') !== 'percent')->required(fn (Get $get) => $get('type') !== 'percent'),
                Toggle::make('free_shipping')->label('Oferă și livrare gratuită')->inline(false),
            ]),
            Section::make('Valabilitate')->columnSpan(1)->schema([
                Toggle::make('is_active')->label('Activ')->default(true),
                DateTimePicker::make('starts_at')->label('Începe')->seconds(false)->native(false),
                DateTimePicker::make('expires_at')->label('Expiră')->seconds(false)->native(false),
                TextInput::make('usage_limit')->label('Limită totală de folosiri')->numeric(),
                TextInput::make('usage_limit_per_user')->label('Limită per client')->numeric(),
            ]),
            Section::make('Restricții')->columnSpanFull()->columns(2)->collapsed()->schema([
                Fields::money('min_subtotal', 'Valoare minimă a coșului'), Fields::money('max_subtotal', 'Valoare maximă a coșului'),
                Toggle::make('individual_use')->label('Nu se cumulează cu alte cupoane')->default(true),
                Toggle::make('exclude_sale_items')->label('Nu se aplică produselor deja reduse'),
                Select::make('product_ids')->label('Doar pentru produsele')->multiple()->searchable()
                    ->getSearchResultsUsing(fn (string $s) => \App\Models\Product::query()->where('name', 'like', "%{$s}%")->orWhere('sku', $s)->limit(30)->pluck('name', 'id')->all())
                    ->getOptionLabelsUsing(fn (array $values) => \App\Models\Product::query()->whereIn('id', $values)->pluck('name', 'id')->all()),
                Select::make('excluded_product_ids')->label('Exceptând produsele')->multiple()->searchable()
                    ->getSearchResultsUsing(fn (string $s) => \App\Models\Product::query()->where('name', 'like', "%{$s}%")->orWhere('sku', $s)->limit(30)->pluck('name', 'id')->all())
                    ->getOptionLabelsUsing(fn (array $values) => \App\Models\Product::query()->whereIn('id', $values)->pluck('name', 'id')->all()),
                Select::make('category_ids')->label('Doar în categoriile')->multiple()->options(fn () => ProductResource::categoryOptions())->searchable(),
                Select::make('excluded_category_ids')->label('Exceptând categoriile')->multiple()->options(fn () => ProductResource::categoryOptions())->searchable(),
                Select::make('brand_ids')->label('Doar pentru brandurile')->multiple()->options(fn () => \App\Models\Brand::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                TagsInput::make('allowed_emails')->label('Doar pentru adresele de email')->placeholder('client@exemplu.ro sau *@firma.ro'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')->label('Cod')->searchable()->weight('bold')->copyable()->fontFamily('mono'),
                TextColumn::make('value')->label('Reducere')->state(fn (Coupon $c) => $c->summary()),
                TextColumn::make('used')->label('Folosit')->state(fn (Coupon $c) => $c->used_count.($c->usage_limit ? ' / '.$c->usage_limit : '')),
                TextColumn::make('expires_at')->label('Expiră')->date('d.m.Y')->placeholder('nelimitat')->color(fn (Coupon $c) => $c->expires_at?->isPast() ? 'danger' : null),
                ToggleColumn::make('is_active')->label('Activ'),
                TextColumn::make('description')->label('Descriere')->toggleable()->limit(40),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Activ')])
            ->recordActions([EditAction::make()->iconButton()])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCoupons::route('/'), 'create' => Pages\CreateCoupon::route('/create'), 'edit' => Pages\EditCoupon::route('/{record}/edit')];
    }
}
