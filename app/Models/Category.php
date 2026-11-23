<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_visible' => 'boolean', 'in_menu' => 'boolean', 'is_featured' => 'boolean'];

    protected static function booted(): void
    {
        // Calea din URL urmeaza mereu parintii; la mutare se recalculeaza si pentru copii.
        static::saving(function (Category $c) {
            $parent = $c->parent_id ? static::query()->find($c->parent_id) : null;
            $c->path = trim(($parent ? $parent->path.'/' : '').$c->slug, '/');
            $c->depth = $parent ? $parent->depth + 1 : 0;
        });
        static::saved(function (Category $c) {
            if ($c->wasChanged(['path', 'parent_id', 'slug'])) {
                $c->children()->get()->each->save();
            }
            Cache::forget('catalog.tree');
        });
        static::deleted(fn () => Cache::forget('catalog.tree'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('is_primary');
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_visible', true);
    }

    public function path(): string
    {
        return '/categorie-produs/'.$this->path.'/';
    }

    /** ID-ul categoriei si al tuturor subcategoriilor (un produs din „Hrana uscata” apare si in „Caini”). @return list<int> */
    public function descendantIds(): array
    {
        $all = static::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [$this->id];
        $stack = [$this->id];
        while ($stack) {
            foreach ($all->get(array_pop($stack), collect()) as $child) {
                $ids[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    /** Lantul de parinti, de la radacina. @return Collection<int, Category> */
    public function ancestors(): Collection
    {
        $chain = collect();
        $node = $this->parent;
        while ($node) {
            $chain->prepend($node);
            $node = $node->parent;
        }

        return $chain;
    }

    /** Arborele vizibil pentru meniu, citit o singura data pe cerere. @return Collection<int, Category> */
    public static function tree(): Collection
    {
        return once(fn () => static::query()->visible()->where('in_menu', true)->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->visible()->where('in_menu', true)->with(['children' => fn ($q) => $q->visible()->where('in_menu', true)])])
            ->orderBy('sort')->orderBy('name')->get());
    }
}
