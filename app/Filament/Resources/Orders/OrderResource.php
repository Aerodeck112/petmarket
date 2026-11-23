<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Models\Order;
use App\Services\Shop\OrderService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Comenzile: lista cu tab-uri pe status si pagina comenzii (echivalentul WooCommerce → Comenzi). */
class OrderResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Order::class;

    protected static ?string $slug = 'orders';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|UnitEnum|null $navigationGroup = 'Vânzări';

    protected static ?string $navigationLabel = 'Comenzi';

    protected static ?string $modelLabel = 'comandă';

    protected static ?string $pluralModelLabel = 'comenzi';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function permission(): string
    {
        return Permission::ORDERS_VIEW;
    }

    protected static function allows(string $action, ?Model $record = null): bool
    {
        $user = auth()->user();
        if (in_array($action, ['viewAny', 'view'], true)) {
            return (bool) $user?->can(Permission::ORDERS_VIEW);
        }
        // Comenzile nu se creeaza si nu se sterg din lista; se modifica doar prin actiunile paginii.
        return in_array($action, ['update'], true) && (bool) $user?->can(Permission::ORDERS_MANAGE);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Order::query()->whereIn('status', [OrderStatus::Processing, OrderStatus::Pending, OrderStatus::OnHold])->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Comenzi de procesat';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'email', 'phone', 'tracking_number'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Client' => $record->customerName(), 'Total' => money($record->total), 'Status' => $record->status->label()];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('items'))
            ->columns([
                TextColumn::make('number')->label('Comanda')->searchable()->sortable()->weight('bold')
                    ->description(fn (Order $o) => $o->created_at->format('d.m.Y H:i')),
                TextColumn::make('customer')->label('Client')->state(fn (Order $o) => $o->customerName())
                    ->description(fn (Order $o) => trim(($o->shipping['city'] ?? '').', '.($o->shipping['county'] ?? ''), ', '))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn ($query) => $query->where('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('billing->first_name', 'like', "%{$search}%")->orWhere('billing->last_name', 'like', "%{$search}%"))),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => $state->color())->icon(fn (OrderStatus $state) => $state->icon()),
                TextColumn::make('payment_method')->label('Plată')->formatStateUsing(fn (PaymentMethod $state) => $state->short())
                    ->description(fn (Order $o) => $o->payment_status->label()),
                TextColumn::make('items_count')->label('Produse')->alignCenter(),
                TextColumn::make('total')->label('Total')->formatStateUsing(fn ($state) => money($state))->sortable()->alignEnd()->weight('bold'),
                TextColumn::make('tracking_number')->label('AWB')->placeholder('—')->toggleable()->copyable()
                    ->description(fn (Order $o) => $o->ecolet_order_to_send_id && ! $o->tracking_number ? 'în Ecolet' : null),
                TextColumn::make('coupon_code')->label('Cupon')->badge()->color('info')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')->label('Sursă')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payment_method')->label('Metodă de plată')->options(PaymentMethod::options()),
                SelectFilter::make('payment_status')->label('Încasare')->options(PaymentStatus::options()),
                Filter::make('period')->schema([
                    DatePicker::make('from')->label('De la'), DatePicker::make('until')->label('Până la'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'De la '.\Illuminate\Support\Carbon::parse($data['from'])->format('d.m.Y') : null,
                        ($data['until'] ?? null) ? 'Până la '.\Illuminate\Support\Carbon::parse($data['until'])->format('d.m.Y') : null,
                    ])),
            ])
            ->recordUrl(fn (Order $o) => static::getUrl('view', ['record' => $o]))
            ->recordActions([ViewAction::make()->label('Deschide')])
            ->toolbarActions([
                BulkAction::make('status')->label('Schimbă statusul')->icon('heroicon-o-arrow-path')
                    ->visible(fn () => auth()->user()?->can(Permission::ORDERS_MANAGE))
                    ->schema([Select::make('status')->label('Status nou')->options(OrderStatus::options())->required()])
                    ->action(function (Collection $records, array $data) {
                        $ok = $skipped = 0;
                        foreach ($records as $order) {
                            try {
                                app(OrderService::class)->changeStatus($order, OrderStatus::from($data['status']));
                                $ok++;
                            } catch (\InvalidArgumentException) {
                                $skipped++;
                            }
                        }
                        Notification::make()->title("Actualizate: {$ok}".($skipped ? ", sărite (tranziție nepermisă): {$skipped}" : ''))->success()->send();
                    })->deselectRecordsAfterCompletion(),
                BulkAction::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Collection $records) => response()->streamDownload(function () use ($records) {
                        $out = fopen('php://output', 'w');
                        fwrite($out, "\xEF\xBB\xBF");
                        fputcsv($out, ['Comanda', 'Data', 'Client', 'Email', 'Telefon', 'Județ', 'Localitate', 'Status', 'Plată', 'Total', 'AWB'], ';');
                        foreach ($records as $o) {
                            fputcsv($out, [$o->number, $o->created_at->format('d.m.Y H:i'), $o->customerName(), $o->email, $o->phone, $o->shipping['county'] ?? '',
                                $o->shipping['city'] ?? '', $o->status->label(), $o->payment_method->short(), number_format($o->total / 100, 2, ',', ''), $o->tracking_number], ';');
                        }
                        fclose($out);
                    }, 'comenzi-'.now()->format('Y-m-d').'.csv')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOrders::route('/'), 'view' => Pages\ViewOrder::route('/{record}')];
    }
}
