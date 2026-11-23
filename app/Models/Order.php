<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => OrderStatus::class, 'payment_status' => PaymentStatus::class, 'payment_method' => PaymentMethod::class,
        'billing' => 'array', 'shipping' => 'array', 'marketing_opt_in' => 'boolean', 'stock_reduced' => 'boolean',
        'accepted_terms_at' => 'datetime', 'paid_at' => 'datetime', 'shipped_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function customerName(): string
    {
        $b = $this->billing ?? [];

        return trim(($b['first_name'] ?? '').' '.($b['last_name'] ?? '')) ?: $this->email;
    }

    /** Adresa pe o singura linie, pentru liste si etichete. */
    public static function addressLine(?array $a): string
    {
        $a ??= [];

        return collect([$a['address_1'] ?? null, $a['address_2'] ?? null, $a['city'] ?? null, $a['county'] ?? null, $a['postcode'] ?? null])
            ->filter()->implode(', ');
    }

    public function itemsCount(): int
    {
        return (int) $this->items->sum('qty');
    }

    /** Pagina publica a comenzii (confirmare), accesibila doar cu tokenul aleatoriu. */
    public function publicUrl(): string
    {
        return site_url('/comanda/'.$this->token.'/');
    }

    public function trackingUrl(): ?string
    {
        if (! $this->tracking_number) {
            return null;
        }
        $awb = rawurlencode($this->tracking_number);

        return match ($this->carrier) {
            'sameday' => 'https://sameday.ro/#awb='.$awb,
            'fan' => 'https://www.fancourier.ro/awb-tracking/?tracking='.$awb,
            'cargus' => 'https://www.cargus.ro/personal/urmareste-coletul/?tracking_number='.$awb,
            'dpd' => 'https://tracking.dpd.ro/?shipmentNumber='.$awb,
            'gls' => 'https://gls-group.com/RO/ro/urmarire-colet?match='.$awb,
            default => null,
        };
    }
}
