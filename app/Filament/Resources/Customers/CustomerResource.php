<?php

namespace App\Filament\Resources\Customers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Concerns\GatedByPermission;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Clientii cu cont: comenzi, total cheltuit, adrese, note interne. Comenzile fara cont raman doar in Comenzi. */
class CustomerResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'customers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Clienți';

    protected static ?string $navigationLabel = 'Clienți';

    protected static ?string $modelLabel = 'client';

    protected static ?string $pluralModelLabel = 'clienți';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function permission(): string
    {
        return Permission::CUSTOMERS_VIEW;
    }

    protected static function allows(string $action, ?Model $record = null): bool
    {
        $user = auth()->user();
        if (in_array($action, ['viewAny', 'view'], true)) {
            return (bool) $user?->can(Permission::CUSTOMERS_VIEW);
        }

        return in_array($action, ['update', 'create'], true) && (bool) $user?->can(Permission::CUSTOMERS_MANAGE);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', Role::staff()));
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Date client')->columnSpan(2)->columns(2)->schema([
                TextInput::make('first_name')->label('Prenume'), TextInput::make('last_name')->label('Nume'),
                TextInput::make('name')->label('Nume afișat')->required(),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->label('Telefon'),
                Toggle::make('is_active')->label('Cont activ')->default(true)->inline(false),
                Textarea::make('internal_notes')->label('Note interne (nu le vede clientul)')->rows(3)->columnSpanFull(),
            ]),
            Section::make('Rezumat')->columnSpan(1)->schema([
                Placeholder::make('orders')->label('Comenzi')->content(fn (?User $record) => $record?->orders_count ?? 0),
                Placeholder::make('spent')->label('Total cheltuit')->content(fn (?User $record) => money($record?->total_spent ?? 0)),
                Placeholder::make('since')->label('Client din')->content(fn (?User $record) => $record?->created_at?->format('d.m.Y') ?? '—'),
                Placeholder::make('login')->label('Ultima autentificare')->content(fn (?User $record) => $record?->last_login_at?->diffForHumans() ?? '—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label('Client')->searchable(['name', 'email', 'phone'])->weight('medium')->description(fn (User $u) => $u->email),
                TextColumn::make('phone')->label('Telefon')->placeholder('—'),
                TextColumn::make('orders_count')->label('Comenzi')->sortable()->alignEnd(),
                TextColumn::make('total_spent')->label('Total')->formatStateUsing(fn ($state) => money($state))->sortable()->alignEnd(),
                TextColumn::make('created_at')->label('Cont creat')->date('d.m.Y')->sortable(),
            ])
            ->filters([
                Filter::make('buyers')->label('Cu comenzi')->query(fn (Builder $query) => $query->where('orders_count', '>', 0)),
                Filter::make('legacy')->label('Importați din WooCommerce')->query(fn (Builder $query) => $query->whereNotNull('wc_customer_id')),
            ])
            ->recordActions([EditAction::make()->iconButton()]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\OrdersRelationManager::class, RelationManagers\AddressesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCustomers::route('/'), 'create' => Pages\CreateCustomer::route('/create'), 'edit' => Pages\EditCustomer::route('/{record}/edit')];
    }
}
