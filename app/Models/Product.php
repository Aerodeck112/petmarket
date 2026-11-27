<?php

namespace App\Models;

use App\Enums\StockStatus;
use App\Models\Concerns\HasSalePrice;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasSalePrice, SoftDeletes;

    public const TYPES = ['simple' => 'Simplu', 'variable' => 'Cu variante'];

    public const STATUSES = ['published' => 'Publicat', 'draft' => 'Ciornă', 'private' => 'Privat'];

    protected $guarded = ['id'];

    protected $casts = [
        'sale_starts_at' => 'datetime', 'sale_ends_at' => 'datetime', 'published_at' => 'datetime',
        'manage_stock' => 'boolean', 'allow_backorders' => 'boolean', 'is_featured' => 'boolean', 'is_visible' => 'boolean', 'on_sale' => 'boolean',
        'stock_status' => StockStatus::class, 'rating_avg' => 'float', 'vat_rate' => 'float', 'sold_online' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            if ($p->type === 'simple') {
                $p->price = $p->effectivePrice();
                $p->price_max = $p->price;
                $p->on_sale = $p->saleIsActive();
                if ($p->manage_stock && $p->stock_qty !== null) {
                    $p->stock_status = $p->stock_qty > 0 ? StockStatus::InStock : ($p->allow_backorders ? StockStatus::OnBackorder : StockStatus::OutOfStock);
                }
            }
            // Un produs cu restrictie (reteta, uz veterinar, doar la sediu) nu poate fi pus in cos.
            if ($p->sale_restriction) {
                $p->sold_online = false;
            }
            if ($p->status === 'published' && ! $p->published_at) {
                $p->published_at = now();
            }
        });
        static::saved(function (Product $p) {
            if ($p->type === 'simple' && ($p->wasRecentlyCreated || $p->wasChanged('price')) && $p->price !== null) {
                PriceHistory::record($p->id, null, $p->price);
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('is_primary');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort')->orderBy('id');
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('sort')->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attribute')->withPivot(['for_variations', 'is_visible', 'sort'])->orderByPivot('sort');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product');
    }

    public function upsells(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_links', 'product_id', 'linked_id')->wherePivot('type', 'upsell')->withPivot('sort');
    }

    public function crossSells(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_links', 'product_id', 'linked_id')->wherePivot('type', 'crosssell')->withPivot('sort');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->where('is_visible', true);
    }

    public function scopeInStock(Builder $q): Builder
    {
        return $q->where('stock_status', '!=', StockStatus::OutOfStock->value);
    }

    public function path(): string
    {
        return '/produs/'.$this->slug.'/';
    }

    public function isVariable(): bool
    {
        return $this->type === 'variable';
    }

    public function isPurchasable(): bool
    {
        return $this->status === 'published' && $this->sold_online && $this->price !== null && $this->price > 0 && $this->stock_status !== StockStatus::OutOfStock;
    }

    public const RESTRICTIONS = [
        'rx' => 'Medicament cu rețetă veterinară',
        'vet' => 'Uz exclusiv al medicului veterinar',
        'location' => 'Se vinde doar la sediu',
    ];

    /** Eticheta scurta de pe cardul produsului. */
    public function restrictionSticker(): string
    {
        return match ($this->sale_restriction) {
            'vet' => 'Uz veterinar',
            'location' => 'Doar la sediu',
            default => 'Cu rețetă',
        };
    }

    /** Textul explicativ de pe pagina produsului (editabil din Setări magazin). */
    public function restrictionNotice(): string
    {
        $key = match ($this->sale_restriction) {
            'vet' => 'commerce.not_online_notice_vet',
            'location' => 'commerce.not_online_notice_location',
            default => 'commerce.not_online_notice',
        };
        $address = trim(Settings::get('shop.address').', '.Settings::get('shop.city'), ', ');

        return str_replace(':adresa', $address, (string) Settings::get($key));
    }

    public function vatRate(): float
    {
        return (float) ($this->vat_rate ?? Settings::get('commerce.vat_rate'));
    }

    public function isNew(): bool
    {
        return $this->published_at && $this->published_at->gt(now()->subDays((int) Settings::get('commerce.new_badge_days')));
    }

    public function primaryCategory(): ?Category
    {
        return $this->categories->sortByDesc(fn ($c) => [$c->pivot->is_primary, $c->depth])->first();
    }

    /** Recalculeaza pretul minim/maxim, reducerea si stocul unui produs variabil din variantele lui. */
    public function syncFromVariants(): void
    {
        if (! $this->isVariable()) {
            return;
        }
        $variants = $this->variants()->where('is_active', true)->get();
        $prices = $variants->map->effectivePrice()->filter(fn ($p) => $p !== null);
        $this->price = $prices->min();
        $this->price_max = $prices->max();
        $this->on_sale = $variants->contains(fn (ProductVariant $v) => $v->saleIsActive());
        $this->stock_status = $variants->contains(fn ($v) => $v->stock_status === StockStatus::InStock) ? StockStatus::InStock
            : ($variants->contains(fn ($v) => $v->stock_status === StockStatus::OnBackorder) ? StockStatus::OnBackorder : StockStatus::OutOfStock);
        $this->stock_qty = $variants->every(fn ($v) => $v->manage_stock) ? (int) $variants->sum('stock_qty') : null;
        $this->saveQuietly();
    }

    /** Cel mai mic pret din ultimele 30 de zile dinaintea reducerii (Directiva Omnibus). */
    /**
     * Prețul de referință afișat tăiat la o reducere (Omnibus, OG 21/1992 art. 16^1): cel mai mic preț din ultimele
     * 30 de zile dinaintea reducerii; fără istoric, prețul normal. Null dacă nu există o reducere reală față de el.
     */
    public function referencePrice(): ?int
    {
        if ($this->isVariable() || ! $this->on_sale || ! $this->price) {
            return null;
        }
        $reference = $this->lowestPrice30() ?? $this->regular_price;

        return $reference && $reference > $this->price ? (int) $reference : null;
    }

    /** Procentul reducerii calculat față de prețul de referință Omnibus. */
    public function referencePercent(): ?int
    {
        $reference = $this->referencePrice();

        return $reference ? (int) round(100 - $this->price * 100 / $reference) : null;
    }

    public function lowestPrice30(?int $variantId = null): ?int
    {
        return PriceHistory::lowestBefore($this->id, $variantId, 30);
    }

    public function refreshRating(): void
    {
        $approved = $this->reviews()->where('status', 'approved');
        $this->rating_count = (clone $approved)->count();
        $this->rating_avg = round((float) (clone $approved)->avg('rating'), 2);
        $this->saveQuietly();
    }
}
