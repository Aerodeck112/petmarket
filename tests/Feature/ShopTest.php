<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PriceHistory;
use App\Models\ShippingMethod;
use App\Services\Shop\Line;
use App\Services\Shop\OrderService;
use App\Services\Shop\PricingEngine;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

/** Regulile comerciale preluate din magazinul WooCommerce: livrare, TVA, cupoane, stoc, statusuri, Omnibus. */
class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedShop();
        Mail::fake();
    }

    private function courier(): ShippingMethod
    {
        return ShippingMethod::query()->where('code', 'curier')->firstOrFail();
    }

    public function test_shipping_costs_23_lei_and_is_free_from_1000_lei_with_vat(): void
    {
        $pricing = app(PricingEngine::class);
        $cheap = $pricing->quote([new Line($this->product(['regular_price' => 5000]), null, 2)], null, $this->courier(), PaymentMethod::Cod);
        $this->assertSame(10000, $cheap->subtotal);
        $this->assertSame(2300, $cheap->shipping);
        $this->assertSame(12300, $cheap->total);
        $this->assertSame(90000, $cheap->freeShippingRemaining);
        // Livrarea nu are TVA (ca in magazinul vechi); TVA-ul de 21% e extras din pretul produselor.
        $this->assertSame((int) round(10000 - 10000 / 1.21), $cheap->vat);

        $big = $pricing->quote([new Line($this->product(['regular_price' => 100000]), null, 1)], null, $this->courier(), PaymentMethod::Cod);
        $this->assertSame(0, $big->shipping);
        $this->assertTrue($big->freeShipping);
        $this->assertSame(100000, $big->total);
    }

    public function test_reduced_vat_rate_per_product_is_respected(): void
    {
        $quote = app(PricingEngine::class)->quote([new Line($this->product(['regular_price' => 102487, 'vat_rate' => 11]), null, 1)], null, $this->courier(), PaymentMethod::Cod);
        $this->assertSame(10156, $quote->vat); // Atopivet 1024,87 = 923,31 + 101,56 TVA 11%
    }

    public function test_coupon_rules(): void
    {
        $food = Category::query()->create(['name' => 'Hrană', 'slug' => 'hrana', 'path' => 'hrana']);
        $inFood = $this->product(['regular_price' => 20000]);
        $inFood->categories()->attach($food);
        $other = $this->product(['regular_price' => 10000]);
        $pricing = app(PricingEngine::class);

        Coupon::query()->create(['code' => 'hrana10', 'type' => 'percent', 'percent' => 10, 'category_ids' => [$food->id]]);
        $q = $pricing->quote([new Line($inFood->load('categories'), null, 1), new Line($other->load('categories'), null, 1)], 'HRANA10', $this->courier());
        $this->assertNull($q->couponError);
        $this->assertSame(2000, $q->discount, 'Reducerea se aplica doar produsului din categorie.');

        Coupon::query()->create(['code' => 'MIN500', 'type' => 'fixed_cart', 'amount' => 5000, 'min_subtotal' => 50000]);
        $this->assertStringContainsString('minimum', (string) $pricing->quote([new Line($other, null, 1)], 'MIN500', $this->courier())->couponError);

        Coupon::query()->create(['code' => 'EXPIRAT', 'type' => 'percent', 'percent' => 5, 'expires_at' => now()->subDay()]);
        $this->assertSame('Cuponul a expirat.', $pricing->quote([new Line($other, null, 1)], 'EXPIRAT')->couponError);

        Coupon::query()->create(['code' => 'LIVRARE', 'type' => 'fixed_cart', 'amount' => 100, 'free_shipping' => true]);
        $this->assertSame(0, $pricing->quote([new Line($other, null, 1)], 'LIVRARE', $this->courier())->shipping);

        Coupon::query()->create(['code' => 'FIRMA', 'type' => 'percent', 'percent' => 5, 'allowed_emails' => ['*@firma.ro']]);
        $this->assertNotNull($pricing->quote([new Line($other, null, 1)], 'FIRMA', null, null, 'ana@gmail.com')->couponError);
        $this->assertNull($pricing->quote([new Line($other, null, 1)], 'FIRMA', null, null, 'ana@firma.ro')->couponError);

        $this->assertStringContainsString('nu există', (string) $pricing->quote([new Line($other, null, 1)], 'NUEXISTA')->couponError);
    }

    public function test_placing_an_order_reduces_stock_and_cancel_restores_it(): void
    {
        $product = $this->product(['stock_qty' => 5]);
        $order = $this->placeOrder($product, 2);

        $this->assertStringStartsWith('PM1000', $order->number);
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertSame(3, $product->fresh()->stock_qty);
        $this->assertSame(2, $product->fresh()->sold_count);
        $this->assertSame(1, $order->items()->count());

        app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled);
        $this->assertSame(5, $product->fresh()->stock_qty);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertSame(2, \App\Models\StockMovement::query()->count());
    }

    public function test_cannot_sell_more_than_stock_or_products_not_sold_online(): void
    {
        $product = $this->product(['stock_qty' => 1]);
        $this->expectException(InvalidArgumentException::class);
        $this->placeOrder($product, 2);
    }

    public function test_prescription_products_cannot_be_added_to_cart(): void
    {
        $rx = $this->product(['sold_online' => false]);
        $this->postJson('/cos/adauga', ['product_id' => $rx->id])->assertStatus(422)->assertJsonFragment(['ok' => false]);
        $this->get($rx->path())->assertOk()->assertSee('Produs care nu se vinde online')->assertDontSee('Adaugă în coș');
    }

    public function test_status_transitions_are_enforced_and_completion_marks_cod_as_paid(): void
    {
        $order = $this->placeOrder();
        $service = app(OrderService::class);
        try {
            $service->changeStatus($order, OrderStatus::Pending);
            $this->fail('Tranziția ar fi trebuit refuzată.');
        } catch (InvalidArgumentException) {
        }
        $service->setTracking($order, 'sameday', 'AWB123');
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        $service->changeStatus($order->fresh(), OrderStatus::Completed);
        $this->assertSame('paid', $order->fresh()->payment_status->value);
    }

    public function test_bank_transfer_orders_wait_for_payment(): void
    {
        $order = $this->placeOrder(null, 1, PaymentMethod::Transfer);
        $this->assertSame(OrderStatus::OnHold, $order->status);
        app(OrderService::class)->markPaid($order, 'transfer', 'OP 12');
        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);
    }

    public function test_omnibus_lowest_price_before_the_sale(): void
    {
        $product = $this->product(['regular_price' => 10000]);
        PriceHistory::query()->where('product_id', $product->id)->update(['recorded_at' => now()->subDays(40)]);
        PriceHistory::query()->create(['product_id' => $product->id, 'price' => 9000, 'recorded_at' => now()->subDays(20)]);
        PriceHistory::query()->create(['product_id' => $product->id, 'price' => 10000, 'recorded_at' => now()->subDays(10)]);
        $product->update(['sale_price' => 7000]);

        $this->assertTrue($product->fresh()->on_sale);
        $this->assertSame(7000, $product->fresh()->price);
        $this->assertSame(9000, $product->lowestPrice30());
        $this->get($product->path())->assertSee('Cel mai mic preț din ultimele 30 de zile', false);
    }

    public function test_variable_products_take_min_max_price_from_variants(): void
    {
        $product = $this->product(['type' => 'variable', 'regular_price' => null, 'manage_stock' => false]);
        $product->variants()->create(['label' => '2 kg', 'regular_price' => 8000, 'manage_stock' => true, 'stock_qty' => 3]);
        $product->variants()->create(['label' => '12 kg', 'regular_price' => 30000, 'sale_price' => 27000, 'manage_stock' => true, 'stock_qty' => 0]);
        $product->syncFromVariants();

        $this->assertSame(8000, $product->price);
        $this->assertSame(27000, $product->price_max);
        $this->assertTrue($product->on_sale);
        $this->assertSame(StockStatus::InStock, $product->stock_status);
        $this->postJson('/cos/adauga', ['product_id' => $product->id])->assertStatus(422);
        $this->postJson('/cos/adauga', ['product_id' => $product->id, 'variant_id' => $product->variants()->first()->id])->assertOk();
    }

    public function test_full_checkout_over_http_creates_order_and_account(): void
    {
        $product = $this->product(['regular_price' => 4500]);
        $this->postJson('/cos/adauga', ['product_id' => $product->id, 'qty' => 2])->assertOk()->assertJson(['count' => 2]);
        $this->get('/cos')->assertOk()->assertSee($product->name)->assertSee('90,00 lei');
        $this->get('/finalizare')->assertOk()->assertSee('Persoană juridică');

        $this->post('/finalizare', $this->checkoutData(['terms' => null]))->assertSessionHasErrors('terms');
        $this->post('/finalizare', $this->checkoutData(['customer_type' => 'pj']))->assertSessionHasErrors(['company', 'cui']);
        $this->post('/finalizare', $this->checkoutData(['phone' => '123']))->assertSessionHasErrors('phone');

        $response = $this->post('/finalizare', $this->checkoutData(['create_account' => '1', 'password' => 'ParolaBuna2026']));
        $order = Order::query()->sole();
        $response->assertRedirect($order->publicUrl());
        $this->assertSame(9000 + 2300, $order->total);
        $this->assertNotNull($order->user_id);
        $this->assertAuthenticated();
        $this->get($order->publicUrl())->assertOk()->assertSee($order->number)->assertSee('Mulțumim');
        $this->get('/contul-meu/comenzi')->assertOk()->assertSee($order->number);
        $this->assertSame(0, app(\App\Services\Shop\CartService::class)->count());
    }

    public function test_free_shipping_threshold_is_editable(): void
    {
        Settings::set('commerce.free_shipping_threshold', 5000);
        $q = app(PricingEngine::class)->quote([new Line($this->product(['regular_price' => 6000]), null, 1)], null, $this->courier());
        $this->assertSame(0, $q->shipping);
    }
}
