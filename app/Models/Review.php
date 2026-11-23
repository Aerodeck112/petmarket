<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    public const STATUSES = ['pending' => 'În așteptare', 'approved' => 'Aprobată', 'rejected' => 'Respinsă'];

    protected $guarded = ['id'];

    protected $casts = ['verified_owner' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn (Review $r) => $r->product?->refreshRating());
        static::deleted(fn (Review $r) => $r->product?->refreshRating());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
