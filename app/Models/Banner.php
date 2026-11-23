<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Banner extends Model
{
    public const PLACEMENTS = ['hero' => 'Slider prima pagină', 'promo' => 'Bandă promoțională', 'category' => 'Antet categorie'];

    public const THEMES = ['cobalt' => 'Albastru', 'sun' => 'Galben', 'magenta' => 'Magenta', 'tangerine' => 'Mandarină', 'teal' => 'Verde', 'ink' => 'Închis'];

    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeLive(Builder $q, string $placement): Builder
    {
        return $q->where('placement', $placement)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('sort');
    }
}
