<?php

namespace App\Filament\Resources\Products\Pages;

use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Audit;

/** Ce se intampla dupa salvarea unui produs din admin: variante, atribute, registrul de stoc, jurnal. */
trait SyncsProduct
{
    protected ?int $stockBefore = null;

    protected function rememberStock(): void
    {
        $this->stockBefore = $this->record?->stock_qty;
    }

    protected function syncProduct(): void
    {
        /** @var Product $product */
        $product = $this->record->fresh(['attributeValues', 'variants.values']);

        // Atributele afisate = cele ale valorilor alese; cele folosite de variante sunt marcate separat.
        $variantAttrs = $product->variants->flatMap->values->pluck('attribute_id')->unique()->all();
        $attrIds = $product->attributeValues->pluck('attribute_id')->merge($variantAttrs)->unique();
        $product->attributes()->sync($attrIds->mapWithKeys(fn ($id, $i) => [$id => ['for_variations' => in_array($id, $variantAttrs, true), 'is_visible' => true, 'sort' => $i]])->all());
        if ($variantAttrs) {
            $product->attributeValues()->syncWithoutDetaching($product->variants->flatMap->values->pluck('id')->unique()->all());
        }

        if ($product->isVariable()) {
            $product->variants->each(fn ($v) => $v->save());
            $product->syncFromVariants();
        }

        if ($product->manage_stock && ! $product->isVariable() && $this->stockBefore !== $product->stock_qty) {
            StockMovement::query()->create(['product_id' => $product->id, 'change' => (int) $product->stock_qty - (int) $this->stockBefore,
                'stock_after' => $product->stock_qty, 'reason' => 'manual', 'user_id' => auth()->id(), 'note' => 'Modificat din fișa produsului']);
        }
        Audit::log('product.saved', $product, 'Produs salvat: '.$product->name);
    }
}
