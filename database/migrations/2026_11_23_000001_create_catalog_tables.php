<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogul magazinului. Toate sumele sunt in bani (intregi), cu TVA inclus, ca in magazinul vechi
 * („TVA inclus”). Coloanele wc_* pastreaza ID-urile WooCommerce pentru import repetabil si redirecturi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->unsignedBigInteger('wc_term_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            // Slug-ul este unic doar sub acelasi parinte: /caini/hrana-uscata/ si /pisici/hrana-uscata/.
            $table->string('slug')->index();
            // Calea completa din URL (/categorie-produs/caini/hrana-uscata/), recalculata la salvare; unica.
            $table->string('path', 500)->unique();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->longText('description')->nullable();
            $table->string('image')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('accent', 20)->nullable();
            $table->boolean('is_visible')->default(true)->index();
            $table->boolean('in_menu')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedInteger('product_count')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->unsignedBigInteger('wc_term_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 20)->default('select'); // select | color | label
            $table->boolean('is_filterable')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedBigInteger('wc_attribute_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedBigInteger('wc_term_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['attribute_id', 'slug']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('wc_term_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('simple')->index(); // simple | variable
            $table->string('status', 20)->default('draft')->index(); // draft | published | private
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku', 100)->nullable()->unique();
            $table->string('gtin', 40)->nullable()->index();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            // Pentru produsele variabile: pretul minim si maxim al variantelor active (pentru sortare si filtre).
            $table->unsignedInteger('regular_price')->nullable();
            $table->unsignedInteger('sale_price')->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->unsignedInteger('price')->nullable()->index();
            $table->unsignedInteger('price_max')->nullable();
            $table->boolean('on_sale')->default(false)->index();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->boolean('manage_stock')->default(true);
            $table->integer('stock_qty')->nullable();
            $table->string('stock_status', 20)->default('instock')->index(); // instock | outofstock | onbackorder
            $table->boolean('allow_backorders')->default(false);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedSmallInteger('min_qty')->default(1);
            $table->unsignedSmallInteger('max_qty')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_visible')->default(true);
            // Produse cu reteta veterinara: pretul se afiseaza, dar nu se pot comanda online (ca in magazinul vechi).
            $table->boolean('sold_online')->default(true)->index();
            $table->text('purchase_note')->nullable();
            $table->string('badge', 40)->nullable();
            $table->unsignedInteger('sold_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->unsignedBigInteger('wc_product_id')->nullable()->unique();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 100)->nullable()->unique();
            $table->string('gtin', 40)->nullable();
            $table->string('label')->nullable();
            $table->unsignedInteger('regular_price')->nullable();
            $table->unsignedInteger('sale_price')->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->boolean('manage_stock')->default(true);
            $table->integer('stock_qty')->nullable();
            $table->string('stock_status', 20)->default('instock');
            $table->unsignedInteger('weight_grams')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedBigInteger('wc_variation_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path')->nullable();
            $table->string('source_url', 700)->nullable();
            $table->string('alt')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'sort']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->primary(['category_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('product_tag', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });

        // Atributele afisate pe produs (tabul „Specificatii”) si cele folosite pentru variante.
        Schema::create('product_attribute', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->boolean('for_variations')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->primary(['product_id', 'attribute_id']);
        });

        // Valorile fiecarui produs (inclusiv ale variantelor), folosite de filtrele din catalog.
        Schema::create('attribute_value_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'attribute_value_id']);
            $table->index('attribute_value_id');
        });

        Schema::create('attribute_value_product_variant', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_variant_id', 'attribute_value_id'], 'avpv_primary');
        });

        Schema::create('product_links', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('linked_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 20); // upsell | crosssell
            $table->unsignedInteger('sort')->default(0);
            $table->primary(['product_id', 'linked_id', 'type']);
        });

        // Istoricul pretului efectiv: „cel mai mic pret din ultimele 30 de zile” la reduceri (Directiva Omnibus).
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price');
            $table->timestamp('recorded_at')->index();
            $table->index(['product_id', 'product_variant_id', 'recorded_at'], 'price_history_lookup');
        });
    }

    public function down(): void
    {
        foreach (['price_history', 'product_links', 'attribute_value_product_variant', 'attribute_value_product', 'product_attribute', 'product_tag',
            'category_product', 'product_images', 'product_variants', 'products', 'tags', 'attribute_values', 'attributes', 'categories', 'brands'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
