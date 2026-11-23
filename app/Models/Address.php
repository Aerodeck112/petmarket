<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_default' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Forma folosita in comenzi (JSON). */
    public function toOrderArray(): array
    {
        return $this->only(['first_name', 'last_name', 'company', 'cui', 'reg_com', 'phone', 'county', 'city', 'address_1', 'address_2', 'postcode', 'country']);
    }
}
