<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Clienti, cos, cupoane, livrare, comenzi, plati, recenzii, stocuri, newsletter si bannere. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 120)->nullable()->after('name');
            $table->string('last_name', 120)->nullable()->after('first_name');
            $table->unsignedBigInteger('wc_customer_id')->nullable()->unique();
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedBigInteger('total_spent')->default(0);
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('shipping'); // billing | shipping
            $table->boolean('is_default')->default(false);
            $table->string('label', 60)->nullable();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('company')->nullable();
            $table->string('cui', 40)->nullable();
            $table->string('reg_com', 60)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('county', 80);
            $table->string('city', 120);
            $table->string('address_1');
            $table->string('address_2')->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country', 2)->default('RO');
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('coupon_code', 60)->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->index('updated_at');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('qty');
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('description')->nullable();
            $table->string('type', 20)->default('percent'); // percent | fixed_cart | fixed_product
            $table->decimal('percent', 5, 2)->nullable();
            $table->unsignedInteger('amount')->nullable();
            $table->boolean('free_shipping')->default(false);
            $table->unsignedInteger('min_subtotal')->nullable();
            $table->unsignedInteger('max_subtotal')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('individual_use')->default(true);
            $table->boolean('exclude_sale_items')->default(false);
            $table->json('product_ids')->nullable();
            $table->json('excluded_product_ids')->nullable();
            $table->json('category_ids')->nullable();
            $table->json('excluded_category_ids')->nullable();
            $table->json('brand_ids')->nullable();
            $table->json('allowed_emails')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('wc_coupon_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('kind', 20)->default('courier'); // courier | locker | pickup
            $table->string('carrier', 40)->nullable();
            $table->string('description')->nullable();
            $table->string('eta', 120)->nullable();
            $table->unsignedInteger('cost')->default(0);
            // null = pragul general din setari; 0 = fara livrare gratuita pentru aceasta metoda.
            $table->unsignedInteger('free_over')->nullable();
            $table->unsignedInteger('cost_per_kg')->default(0);
            $table->unsignedInteger('max_weight_grams')->nullable();
            $table->boolean('cod_allowed')->default(true);
            $table->json('counties')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('token', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->string('payment_method', 30);
            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shipping_method_name')->nullable();
            $table->string('currency', 3)->default('RON');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('shipping_total')->default(0);
            $table->unsignedInteger('fee_total')->default(0);
            $table->unsignedInteger('vat_total')->default(0);
            $table->unsignedInteger('total');
            $table->unsignedInteger('refunded_total')->default(0);
            $table->string('coupon_code', 60)->nullable();
            $table->string('customer_type', 2)->default('pf'); // pf | pj
            $table->string('email');
            $table->string('phone', 60);
            $table->json('billing');
            $table->json('shipping');
            $table->text('customer_note')->nullable();
            $table->string('carrier', 40)->nullable();
            $table->string('tracking_number', 80)->nullable();
            $table->string('invoice_number', 60)->nullable();
            $table->string('invoice_url')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->string('source', 40)->default('site');
            $table->timestamp('accepted_terms_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('stock_reduced')->default(false);
            $table->unsignedBigInteger('wc_order_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
            $table->index('email');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 100)->nullable();
            $table->string('name');
            $table->string('variant_label')->nullable();
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('qty');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('regular_unit_price');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total');
            $table->decimal('vat_rate', 5, 2);
            $table->unsignedInteger('vat_amount');
            $table->unsignedSmallInteger('refunded_qty')->default(0);
        });

        // Istoricul comenzii: schimbari de status, note interne, note catre client, emailuri trimise.
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('note');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_customer_visible')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_ref')->nullable()->index();
            $table->string('kind', 20)->default('charge'); // charge | refund
            $table->unsignedInteger('amount');
            $table->string('status', 30)->default('pending');
            $table->json('payload')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->unsignedInteger('discount');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('change');
            $table->integer('stock_after')->nullable();
            $table->string('reason', 30);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name', 120);
            $table->string('author_email')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->text('reply')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('verified_owner')->default(false);
            $table->unsignedBigInteger('wc_comment_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('source', 40)->default('footer');
            $table->string('token', 64)->unique();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });

        // Bannere: slider-ul de pe prima pagina, promotii intre sectiuni, banner pe categorie.
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 30)->default('hero')->index();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('url')->nullable();
            $table->string('image')->nullable();
            $table->string('image_mobile')->nullable();
            $table->string('theme', 20)->default('cobalt');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['banners', 'newsletter_subscribers', 'wishlist_items', 'reviews', 'stock_movements', 'coupon_usages', 'payments',
            'order_events', 'order_items', 'orders', 'shipping_methods', 'coupons', 'cart_items', 'carts', 'addresses'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['first_name', 'last_name', 'wc_customer_id', 'orders_count', 'total_spent']));
    }
};
