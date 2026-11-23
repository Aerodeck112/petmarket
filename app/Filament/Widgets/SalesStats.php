<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Models\Order;
use App\Models\Product;
use App\Support\Settings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can(Permission::ORDERS_VIEW);
    }

    private const SALES = ['processing', 'shipped', 'completed', 'on_hold'];

    protected function getStats(): array
    {
        $sales = fn ($from, $to = null) => Order::query()->whereIn('status', self::SALES)->where('created_at', '>=', $from)->when($to, fn ($q) => $q->where('created_at', '<', $to));
        $daily = collect(range(13, 0))->map(fn ($d) => (int) $sales(now()->subDays($d)->startOfDay(), now()->subDays($d - 1)->startOfDay())->sum('total') / 100)->all();
        $today = (int) $sales(now()->startOfDay())->sum('total');
        $week = (int) $sales(now()->subDays(7))->sum('total');
        $prevWeek = (int) $sales(now()->subDays(14), now()->subDays(7))->sum('total');
        $month = $sales(now()->subDays(30));
        $monthTotal = (int) (clone $month)->sum('total');
        $monthCount = (clone $month)->count();
        $todo = Order::query()->whereIn('status', ['processing', 'pending', 'on_hold'])->count();
        $low = Product::query()->published()->where('manage_stock', true)->where('stock_qty', '<=', (int) Settings::get('commerce.low_stock_threshold'))->count();
        $trend = $prevWeek ? round(($week - $prevWeek) * 100 / $prevWeek) : null;

        return [
            Stat::make('Vânzări azi', money($today))->description($sales(now()->startOfDay())->count().' comenzi')->chart($daily)->color('primary')->icon('heroicon-o-banknotes'),
            Stat::make('Ultimele 7 zile', money($week))->description($trend === null ? 'față de săptămâna trecută' : ($trend >= 0 ? '+' : '').$trend.'% față de săptămâna trecută')
                ->descriptionIcon($trend !== null && $trend < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')->color($trend !== null && $trend < 0 ? 'danger' : 'success'),
            Stat::make('Ultimele 30 de zile', money($monthTotal))->description($monthCount.' comenzi · medie '.money($monthCount ? intdiv($monthTotal, $monthCount) : 0)),
            Stat::make('De procesat', (string) $todo)->description('comenzi noi sau în așteptare')->color($todo ? 'warning' : 'gray')->icon('heroicon-o-bolt')
                ->url(\App\Filament\Resources\Orders\OrderResource::getUrl('index')),
            Stat::make('Stoc redus', (string) $low)->description('produse publicate')->color($low ? 'danger' : 'gray')->icon('heroicon-o-archive-box-x-mark')
                ->url(\App\Filament\Resources\Products\ProductResource::getUrl('index', ['filters' => ['low' => ['isActive' => true]]])),
        ];
    }
}
