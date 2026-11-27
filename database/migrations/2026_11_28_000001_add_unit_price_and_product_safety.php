<?php

use App\Support\UnitPrice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Actualizarea 0.1.2:
 * - cantitatea netă a produsului (kg sau l), pentru prețul pe unitatea de măsură afișat lângă preț;
 * - datele producătorului pe brand și informațiile de siguranță pe produs, cerute de Regulamentul (UE) 2023/988
 *   privind siguranța generală a produselor (GPSR) pentru produsele vândute online.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('net_quantity', 10, 3)->nullable()->after('weight_grams');
            $table->string('net_unit', 4)->nullable()->after('net_quantity');
            $table->text('safety_info')->nullable()->after('purchase_note');
        });
        Schema::table('brands', function (Blueprint $table) {
            $table->text('manufacturer_info')->nullable();
        });

        // Cantitatea netă pentru produsele vândute la greutate sau volum (după categoria principală).
        $paths = DB::table('category_product')->join('categories', 'categories.id', '=', 'category_product.category_id')
            ->select('category_product.product_id', 'categories.path')->get()->groupBy('product_id');
        DB::table('products')->select('id', 'name')->orderBy('id')->chunk(500, function ($products) use ($paths) {
            foreach ($products as $p) {
                if (! UnitPrice::eligiblePaths($paths->get($p->id, collect())->pluck('path')->all())) {
                    continue;
                }
                if ($found = UnitPrice::detect($p->name)) {
                    DB::table('products')->where('id', $p->id)->update(['net_quantity' => $found[0], 'net_unit' => $found[1]]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['net_quantity', 'net_unit', 'safety_info']);
        });
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('manufacturer_info');
        });
    }
};
