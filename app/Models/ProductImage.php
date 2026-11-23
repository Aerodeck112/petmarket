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

    /** Miniatura de 400 px pentru liste (sau imaginea mare, daca miniatura nu exista). */
    public function thumbUrl(): ?string
    {
        if ($this->path && str_ends_with($this->path, '.webp')) {
            $thumb = preg_replace('/\.webp$/', '-400.webp', $this->path);
            if (is_file(public_path('uploads/'.$thumb))) {
                return asset('uploads/'.$thumb);
            }
        }

        return $this->url();
    }
}
