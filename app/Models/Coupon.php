<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    public const TYPES = ['percent' => 'Procent din coș', 'fixed_cart' => 'Sumă fixă pe coș', 'fixed_product' => 'Sumă fixă pe produs'];

    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'datetime', 'expires_at' => 'datetime', 'free_shipping' => 'boolean', 'individual_use' => 'boolean',
        'exclude_sale_items' => 'boolean', 'is_active' => 'boolean', 'percent' => 'float',
        'product_ids' => 'array', 'excluded_product_ids' => 'array', 'category_ids' => 'array', 'excluded_category_ids' => 'array',
        'brand_ids' => 'array', 'allowed_emails' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(fn (Coupon $c) => $c->code = mb_strtoupper(trim($c->code)));
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function summary(): string
    {
        $value = match ($this->type) {
            'percent' => rtrim(rtrim(number_format((float) $this->percent, 2, ',', ''), '0'), ',').'%',
            default => money((int) $this->amount).($this->type === 'fixed_product' ? ' / produs' : ''),
        };

        return $value.($this->free_shipping ? ' + livrare gratuită' : '');
    }
}
