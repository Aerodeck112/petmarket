<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Vânzări pe zile';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public ?string $filter = '30';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can(Permission::REPORTS_VIEW) || (bool) auth()->user()?->can(Permission::ORDERS_VIEW);
    }

    protected function getFilters(): ?array
    {
        return ['14' => '14 zile', '30' => '30 de zile', '90' => '90 de zile'];
    }

    protected function getData(): array
    {
        $days = (int) $this->filter;
        $rows = Order::query()->whereIn('status', ['processing', 'shipped', 'completed', 'on_hold'])->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->get(['created_at', 'total'])->groupBy(fn ($o) => $o->created_at->format('Y-m-d'));
        $labels = $totals = $counts = [];
        for ($d = $days - 1; $d >= 0; $d--) {
            $day = now()->subDays($d);
            $labels[] = $day->format('d.m');
            $totals[] = round(($rows[$day->format('Y-m-d')] ?? collect())->sum('total') / 100, 2);
            $counts[] = ($rows[$day->format('Y-m-d')] ?? collect())->count();
        }

        return [
            'datasets' => [
                ['label' => 'Vânzări (lei)', 'data' => $totals, 'borderColor' => '#1e3ba8', 'backgroundColor' => 'rgba(30,59,168,.12)', 'fill' => true, 'tension' => 0.35, 'yAxisID' => 'y'],
                ['label' => 'Comenzi', 'data' => $counts, 'type' => 'bar', 'backgroundColor' => 'rgba(255,194,31,.75)', 'borderRadius' => 6, 'yAxisID' => 'y1'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['position' => 'left', 'beginAtZero' => true], 'y1' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false], 'ticks' => ['precision' => 0]]],
            'plugins' => ['legend' => ['display' => true]]];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
