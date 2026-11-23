<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

/** Reducerile cu interval: cand incepe sau se termina una, pretul afisat, filtrele si istoricul Omnibus se actualizeaza. */
class SyncSales extends Command
{
    protected $signature = 'petmarket:sync-sales';

    protected $description = 'Aplică începutul și sfârșitul reducerilor programate.';

    public function handle(): int
    {
        $window = fn ($q) => $q->where(fn ($q) => $q->whereBetween('sale_starts_at', [now()->subHour(), now()])->orWhereBetween('sale_ends_at', [now()->subHour(), now()]));
        $n = 0;
        Product::query()->where('type', 'simple')->where($window)->each(function (Product $p) use (&$n) {
            $p->save();
            $n++;
        });
        ProductVariant::query()->where($window)->with('product')->each(function (ProductVariant $v) use (&$n) {
            $v->save();
            $v->product?->syncFromVariants();
            $n++;
        });
        $this->info("Prețuri actualizate: {$n}");

        return self::SUCCESS;
    }
}
