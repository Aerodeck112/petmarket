<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Legatura comenzii cu Ecolet: comanda de trimis, comanda generata (cu AWB) si ultimul status primit. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('ecolet_order_to_send_id')->nullable()->index();
            $table->unsignedBigInteger('ecolet_order_id')->nullable()->index();
            $table->string('ecolet_status', 80)->nullable();
            $table->string('ecolet_service', 80)->nullable();
            $table->timestamp('ecolet_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['ecolet_order_to_send_id', 'ecolet_order_id', 'ecolet_status', 'ecolet_service', 'ecolet_synced_at']));
    }
};
