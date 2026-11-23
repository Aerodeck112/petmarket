<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Comenzi recente';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can(Permission::ORDERS_VIEW);
    }

    public function table(Table $table): Table
    {
        return $table->query(Order::query()->latest('id')->limit(8))->paginated(false)
            ->columns([
                TextColumn::make('number')->label('Comanda')->weight('bold')->description(fn (Order $o) => $o->created_at->diffForHumans()),
                TextColumn::make('customer')->label('Client')->state(fn (Order $o) => $o->customerName())->description(fn (Order $o) => $o->shipping['city'] ?? ''),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())->color(fn (OrderStatus $state) => $state->color()),
                TextColumn::make('total')->label('Total')->formatStateUsing(fn ($state) => money($state))->alignEnd()->weight('bold'),
            ])
            ->recordUrl(fn (Order $o) => OrderResource::getUrl('view', ['record' => $o]))
            ->emptyStateHeading('Încă nu sunt comenzi')->emptyStateDescription('Hiko stă cu ochii pe ușă.');
    }
}
