<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $guarded = ['id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Imaginea locala daca a fost descarcata, altfel cea din magazinul vechi. */
    public function url(): ?string
    {
        if ($this->path) {
            return asset('uploads/'.$this->path);
        }

        return $this->source_url;
    }
}
