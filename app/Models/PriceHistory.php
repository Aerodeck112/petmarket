<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    protected $table = 'price_history';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['recorded_at' => 'datetime'];

    public static function record(int $productId, ?int $variantId, int $price): void
    {
        static::query()->create(['product_id' => $productId, 'product_variant_id' => $variantId, 'price' => $price, 'recorded_at' => now()]);
    }

    /**
     * Cel mai mic pret practicat in cele N zile dinaintea pretului curent (Directiva Omnibus): fereastra se
     * masoara de la momentul in care a intrat in vigoare pretul actual, nu de azi, ca pretul redus sa nu se
     * compare cu el insusi. Returneaza null daca nu exista istoric (de exemplu imediat dupa import).
     */
    public static function lowestBefore(int $productId, ?int $variantId, int $days): ?int
    {
        $base = fn () => static::query()->where('product_id', $productId)->where('product_variant_id', $variantId);
        $current = $base()->orderByDesc('recorded_at')->orderByDesc('id')->first();
        if (! $current) {
            return null;
        }
        $from = $current->recorded_at->copy()->subDays($days);
        $inWindow = $base()->where('recorded_at', '>=', $from)->where('recorded_at', '<', $current->recorded_at)->min('price');
        $atStart = $base()->where('recorded_at', '<', $from)->orderByDesc('recorded_at')->value('price');
        $candidates = array_filter([$inWindow, $atStart], fn ($v) => $v !== null);

        return $candidates ? (int) min($candidates) : null;
    }
}
