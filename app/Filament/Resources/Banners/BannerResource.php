<?php

namespace App\Filament\Resources\Banners;

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
use App\Filament\Resources\Products\ProductResource;
use App\Models\Banner;

/** Mesajele din bula de pe prima pagina, bannerele promo si antetele de categorie. */
class BannerResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Bannere și promoții';

    protected static ?string $modelLabel = 'banner';

    protected static ?string $pluralModelLabel = 'bannere';

    protected static ?int $navigationSort = 2;

    public static function permission(): string
    {
        return Permission::CMS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(2)->schema([
                Select::make('placement')->label('Unde apare')->options(Banner::PLACEMENTS)->required()->live(),
                Select::make('theme')->label('Culoare')->options(Banner::THEMES)->default('cobalt'),
                TextInput::make('title')->label('Titlu')->required()->maxLength(120)->columnSpanFull(),
                TextInput::make('subtitle')->label('Text')->maxLength(240)->columnSpanFull(),
                TextInput::make('cta_label')->label('Text buton')->maxLength(60),
                TextInput::make('url')->label('Link')->placeholder('/categorie-produs/hrana/')->maxLength(250),
                Select::make('category_id')->label('Categoria')->options(fn () => ProductResource::categoryOptions())->searchable()
                    ->visible(fn (callable $get) => $get('placement') === 'category'),
            ]),
            Section::make('Afișare')->columnSpan(1)->schema([
                FileUpload::make('image')->label('Imagine')->disk('public')->directory('banners')->image()
                    ->formatStateUsing(fn ($state) => $state ? preg_replace('#^uploads/#', '', $state) : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? (str_starts_with(is_array($state) ? reset($state) : $state, 'media/') ? (is_array($state) ? reset($state) : $state) : 'uploads/'.ltrim(preg_replace('#^uploads/#', '', is_array($state) ? reset($state) : $state), '/')) : null),
                Toggle::make('is_active')->label('Activ')->default(true),
                DateTimePicker::make('starts_at')->label('De la')->seconds(false)->native(false),
                DateTimePicker::make('ends_at')->label('Până la')->seconds(false)->native(false),
                TextInput::make('sort')->label('Ordine')->numeric()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->reorderable('sort')->defaultSort('sort')
            ->columns([
                ImageColumn::make('image')->label('')->state(fn (Banner $b) => $b->image ? asset($b->image) : null)->height(44),
                TextColumn::make('title')->label('Titlu')->weight('medium')->description(fn (Banner $b) => $b->subtitle)->wrap(),
                TextColumn::make('placement')->label('Loc')->badge()->formatStateUsing(fn ($state) => Banner::PLACEMENTS[$state] ?? $state),
                TextColumn::make('ends_at')->label('Până la')->date('d.m.Y')->placeholder('—'),
                ToggleColumn::make('is_active')->label('Activ'),
            ])
            ->filters([SelectFilter::make('placement')->label('Loc')->options(Banner::PLACEMENTS)])
            ->recordActions([EditAction::make()->iconButton()])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBanners::route('/'), 'create' => Pages\CreateBanner::route('/create'), 'edit' => Pages\EditBanner::route('/{record}/edit')];
    }
}
