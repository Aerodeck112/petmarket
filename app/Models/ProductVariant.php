<?php

namespace App\Models;

use App\Enums\StockStatus;
use App\Models\Concerns\HasSalePrice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    use HasSalePrice;

    protected $guarded = ['id'];

    protected $casts = [
        'sale_starts_at' => 'datetime', 'sale_ends_at' => 'datetime', 'manage_stock' => 'boolean', 'is_active' => 'boolean',
        'stock_status' => StockStatus::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductVariant $v) {
            $v->price = $v->effectivePrice();
            if ($v->manage_stock && $v->stock_qty !== null) {
                $backorders = (bool) ($v->product?->allow_backorders);
                $v->stock_status = $v->stock_qty > 0 ? StockStatus::InStock : ($backorders ? StockStatus::OnBackorder : StockStatus::OutOfStock);
            }
        });
        static::saved(function (ProductVariant $v) {
            if ($v->wasChanged('price') && $v->price !== null) {
                PriceHistory::record($v->product_id, $v->id, $v->price);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product_variant');
    }

    /** Eticheta afisata: „Gramaj: 2 kg” sau cea salvata explicit. */
    public function displayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        return $this->values->map(fn ($v) => $v->name)->implode(' / ') ?: 'Variantă #'.$this->id;
    }

    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return str_starts_with($this->image, 'http') ? $this->image : asset('uploads/'.$this->image);
    }
}
