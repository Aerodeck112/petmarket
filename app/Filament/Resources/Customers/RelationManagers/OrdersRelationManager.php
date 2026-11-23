<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Comenzi';

    public function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('Comanda')->weight('bold'),
                TextColumn::make('created_at')->label('Data')->dateTime('d.m.Y H:i'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())->color(fn (OrderStatus $state) => $state->color()),
                TextColumn::make('total')->label('Total')->formatStateUsing(fn ($state) => money($state))->alignEnd(),
            ])
            ->recordUrl(fn (Order $o) => OrderResource::getUrl('view', ['record' => $o]));
    }
}
