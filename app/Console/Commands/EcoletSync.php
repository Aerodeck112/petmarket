<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Shipping\EcoletClient;
use App\Services\Shipping\EcoletShipments;
use Illuminate\Console\Command;
use Throwable;

class EcoletSync extends Command
{
    protected $signature = 'petmarket:ecolet-sync';

    protected $description = 'Aduce din Ecolet AWB-urile și statusurile comenzilor trimise.';

    public function handle(EcoletShipments $ecolet): int
    {
        if (! EcoletClient::configured()) {
            return self::SUCCESS;
        }
        // Trimitere automata: comenzile noi (dupa activarea optiunii) ajung singure in „Comenzi de trimis”.
        if (\App\Support\Settings::get('ecolet.auto_send') && ($since = \App\Support\Settings::get('ecolet.auto_since'))) {
            Order::query()->whereNull('ecolet_order_to_send_id')->where('status', OrderStatus::Processing)->where('created_at', '>=', $since)
                ->whereHas('shippingMethod', fn ($q) => $q->where('kind', '!=', 'pickup'))->orderBy('id')->limit(30)
                ->each(function (Order $order) use ($ecolet) {
                    try {
                        $ecolet->push($order);
                    } catch (Throwable $e) {
                        $order->update(['ecolet_status' => 'netrimisă: '.\Illuminate\Support\Str::limit($e->getMessage(), 60)]);
                        report($e);
                    }
                });
        }
        $n = 0;
        Order::query()->whereNotNull('ecolet_order_to_send_id')
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Failed])
            ->where('created_at', '>', now()->subDays(45))->orderBy('id')->limit(100)
            ->each(function (Order $order) use ($ecolet, &$n) {
                try {
                    $n += (int) $ecolet->sync($order);
                } catch (Throwable $e) {
                    report($e);
                }
            });
        $this->info("Comenzi actualizate din Ecolet: {$n}");

        return self::SUCCESS;
    }
}
