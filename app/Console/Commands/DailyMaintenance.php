<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Services\Shop\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DailyMaintenance extends Command
{
    protected $signature = 'petmarket:daily';

    protected $description = 'Șterge coșurile abandonate vechi și anulează comenzile cu plata cu cardul neconfirmată de peste 48 de ore.';

    public function handle(OrderService $orders): int
    {
        $carts = Cart::query()->where('updated_at', '<', now()->subDays(60))->delete();
        $cancelled = 0;
        Order::query()->where('status', OrderStatus::Pending)->where('payment_method', 'card')->where('created_at', '<', now()->subHours(48))
            ->each(function (Order $order) use ($orders, &$cancelled) {
                $orders->changeStatus($order, OrderStatus::Cancelled, 'Anulată automat: plata nu a fost confirmată în 48 de ore.');
                $cancelled++;
            });
        Cache::forever('petmarket.last_daily_run', now()->toIso8601String());
        $this->info("Coșuri șterse: {$carts}, comenzi neplătite anulate: {$cancelled}");

        return self::SUCCESS;
    }
}
