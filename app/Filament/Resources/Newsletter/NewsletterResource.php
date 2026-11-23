<?php

namespace App\Filament\Resources\Newsletter;

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
use App\Models\NewsletterSubscriber;

class NewsletterResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $slug = 'newsletter';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Abonați newsletter';

    protected static ?string $modelLabel = 'abonat';

    protected static ?string $pluralModelLabel = 'abonați';

    protected static ?int $navigationSort = 3;

    public static function permission(): string
    {
        return Permission::SEO_MANAGE;
    }

    protected static function writable(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('name')->label('Nume')->placeholder('—'),
                TextColumn::make('source')->label('Sursă')->badge(),
                TextColumn::make('created_at')->label('Abonat')->date('d.m.Y')->sortable(),
                TextColumn::make('unsubscribed_at')->label('Dezabonat')->date('d.m.Y')->placeholder('—'),
            ])
            ->filters([Filter::make('active')->label('Doar activi')->default()->query(fn (Builder $query) => $query->whereNull('unsubscribed_at'))])
            ->headerActions([
                Action::make('export')->label('Export CSV (Mailchimp, Brevo)')->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => response()->streamDownload(function () {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, ['email', 'name', 'source', 'subscribed_at']);
                        NewsletterSubscriber::query()->whereNull('unsubscribed_at')->orderBy('id')->each(fn ($s) => fputcsv($out, [$s->email, $s->name, $s->source, $s->created_at->toDateString()]));
                        fclose($out);
                    }, 'newsletter-'.now()->format('Y-m-d').'.csv')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNewsletter::route('/')];
    }
}
