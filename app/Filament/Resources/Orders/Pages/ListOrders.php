<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        $counts = Order::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $tabs = [
            'todo' => Tab::make('De procesat')->icon('heroicon-o-bolt')
                ->badge($counts->only(['processing', 'pending', 'on_hold'])->sum() ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['processing', 'pending', 'on_hold'])),
            'all' => Tab::make('Toate'),
        ];
        foreach (OrderStatus::cases() as $s) {
            $tabs[$s->value] = Tab::make($s->label())->badge($counts[$s->value] ?? null)->badgeColor($s->color())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $s->value));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'todo';
    }
}
