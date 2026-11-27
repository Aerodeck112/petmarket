<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Actualizarea 0.1.1: produsele importate inainte de restrictiile de vanzare primesc motivul din clasificarea catalogului
 * (rx = medicament cu reteta, vet = uz exclusiv veterinar, location = doar la sediu) si ies de la vanzarea online.
 * Se completeaza doar produsele fara restrictie, deci nu se pierde nimic setat deja din admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $file = database_path('seeders/data/catalog/classification.json');
        if (! is_file($file)) {
            return;
        }
        $restrictions = collect(json_decode((string) file_get_contents($file), true) ?: [])
            ->map(fn ($c) => $c['restriction'] ?? null)->filter()->groupBy(fn ($r) => $r, preserveKeys: true);

        foreach ($restrictions as $reason => $ids) {
            foreach (array_chunk(array_map('intval', array_keys($ids->all())), 500) as $chunk) {
                DB::table('products')->whereIn('wc_product_id', $chunk)->whereNull('sale_restriction')
                    ->update(['sale_restriction' => $reason, 'sold_online' => false, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Datele raman: restrictiile se pot schimba din admin, produs cu produs.
    }
};
