<?php

namespace App\Filament\Resources\Reviews;

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
use App\Models\Review;

class ReviewResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static string|UnitEnum|null $navigationGroup = 'Clienți';

    protected static ?string $navigationLabel = 'Recenzii';

    protected static ?string $modelLabel = 'recenzie';

    protected static ?string $pluralModelLabel = 'recenzii';

    protected static ?int $navigationSort = 3;

    public static function permission(): string
    {
        return Permission::REVIEWS_MANAGE;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Review::query()->where('status', 'pending')->count();

        return $n ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('status')->label('Stare')->options(Review::STATUSES)->required(),
            Select::make('rating')->label('Notă')->options([1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★'])->required(),
            TextInput::make('author_name')->label('Autor')->required(),
            Toggle::make('verified_owner')->label('A cumpărat produsul')->inline(false),
            Textarea::make('body')->label('Recenzie')->rows(4)->required()->columnSpanFull(),
            Textarea::make('reply')->label('Răspunsul magazinului (public)')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->modifyQueryUsing(fn (Builder $query) => $query->with('product'))
            ->columns([
                TextColumn::make('product.name')->label('Produs')->limit(40)->searchable(),
                TextColumn::make('rating')->label('Notă')->formatStateUsing(fn ($state) => str_repeat('★', $state))->color('warning'),
                TextColumn::make('body')->label('Recenzie')->limit(80)->wrap()->description(fn (Review $r) => $r->author_name.($r->verified_owner ? ' · a cumpărat' : '')),
                TextColumn::make('status')->label('Stare')->badge()->formatStateUsing(fn ($state) => Review::STATUSES[$state])
                    ->color(fn ($state) => ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$state]),
                TextColumn::make('created_at')->label('Data')->date('d.m.Y')->sortable(),
            ])
            ->filters([SelectFilter::make('status')->label('Stare')->options(Review::STATUSES)])
            ->recordActions([
                Action::make('approve')->label('Aprobă')->icon('heroicon-o-check')->color('success')->visible(fn (Review $r) => $r->status !== 'approved')->action(fn (Review $r) => $r->update(['status' => 'approved'])),
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkAction::make('approve')->label('Aprobă')->action(fn (Collection $records) => $records->each->update(['status' => 'approved'])),
                BulkAction::make('reject')->label('Respinge')->color('danger')->action(fn (Collection $records) => $records->each->update(['status' => 'rejected'])),
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListReviews::route('/'), 'edit' => Pages\EditReview::route('/{record}/edit')];
    }
}
