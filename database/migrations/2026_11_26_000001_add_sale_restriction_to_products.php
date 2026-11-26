<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivul pentru care un produs nu se vinde online: rx = medicament cu rețetă veterinară (Legea 160/1998, art. 37;
 * Regulamentul (UE) 2019/6: online doar fără rețetă), vet = uz exclusiv al medicului veterinar (anestezice, sedative,
 * eutanasie), location = se vinde doar la sediu (decizia magazinului: echipamente mari etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sale_restriction', 20)->nullable()->after('sold_online')->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sale_restriction']);
            $table->dropColumn('sale_restriction');
        });
    }
};
