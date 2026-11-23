<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Services\Shipping\EcoletClient;
use App\Services\Shipping\EcoletShipments;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Fluxul Ecolet, cu raspunsurile API-ului simulate dupa documentatia OpenAPI v1.0.3. */
class EcoletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedShop();
        Mail::fake();
        Settings::set('ecolet.enabled', true);
        Settings::set('ecolet.client_id', 'client-123');
        Settings::set('ecolet.client_secret', 'secret-xyz', true);
        Settings::set('ecolet.username', 'office@pet-market.ro');
        Settings::set('ecolet.password', 'parola', true);
    }

    private function fakeEcolet(array $orderStatuses = [['name' => 'new', 'real_name' => 'Date primite']]): void
    {
        Http::fake([
            'panel.ecolet.ro/api/v1/oauth/token' => Http::response(['token_type' => 'Bearer', 'expires_in' => 31536000, 'access_token' => 'jwt-token', 'refresh_token' => 'r']),
            'panel.ecolet.ro/api/v1/locations/ro/localities/*' => fn (Request $r) => Http::response(['localities' => [
                ['id' => 501, 'name' => 'Targu Mures', 'county' => ['id' => 27, 'name' => 'Mures', 'code' => 'MS']],
                ['id' => 777, 'name' => 'Cluj-Napoca', 'county' => ['id' => 12, 'name' => 'Cluj', 'code' => 'CJ']],
            ]]),
            'panel.ecolet.ro/api/v1/add-parcel/save-order-to-send' => Http::response(['order_to_send_id' => 4321]),
            'panel.ecolet.ro/api/v1/order-to-send/4321' => Http::response(['order_to_send' => ['id' => 4321, 'status' => 'ordered', 'order_id' => 99]]),
            'panel.ecolet.ro/api/v1/order/99' => Http::response(['data' => ['id' => 99, 'awb' => '80438360579', 'service' => ['slug' => 'dpd_standard', 'full_name' => 'DPD Standard', 'courier_slug' => 'dpd'], 'statuses' => $orderStatuses]]),
        ]);
    }

    public function test_order_is_sent_with_cod_and_awb_comes_back(): void
    {
        $this->fakeEcolet();
        $this->assertTrue(EcoletClient::configured());
        $order = $this->placeOrder($this->product(['regular_price' => 12345, 'weight_grams' => 2500]));
        $ecolet = app(EcoletShipments::class);

        $this->assertSame(4321, $ecolet->push($order));
        Http::assertSent(function (Request $r) use ($order) {
            if (! str_ends_with($r->url(), '/add-parcel/save-order-to-send')) {
                return false;
            }
            $b = $r->data();

            return $r->hasHeader('Authorization', 'Bearer jwt-token')
                && $b['receiver']['locality_id'] === 501 && $b['receiver']['street_name'] === 'Str. Test' && $b['receiver']['street_number'] === '1'
                && $b['additional_services']['cod']['status'] === true && $b['additional_services']['cod']['amount'] === round($order->total / 100, 2)
                && $b['parcel']['weight'] === 3 && $b['receiver']['phone'] === '0722123456' && str_contains($b['parcel']['observations'], $order->number);
        });

        $this->assertTrue($ecolet->sync($order->fresh()));
        $order->refresh();
        $this->assertSame('80438360579', $order->tracking_number);
        $this->assertSame('dpd', $order->carrier);
        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertSame('DPD Standard', $order->ecolet_service);
        $this->assertStringContainsString('dpd.ro', (string) $order->trackingUrl());
    }

    public function test_delivery_status_completes_the_order(): void
    {
        $this->fakeEcolet([['name' => 'new', 'real_name' => 'Date primite'], ['name' => 'delivered', 'real_name' => 'Livrat']]);
        $order = $this->placeOrder();
        $ecolet = app(EcoletShipments::class);
        $ecolet->push($order);
        $ecolet->sync($order->fresh());
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status->value);
    }

    public function test_street_and_phone_parsing(): void
    {
        $this->assertSame(['Str. Băneasa', '23'], EcoletShipments::splitStreet('Str. Băneasa 23, Spațiu Comercial 2'));
        $this->assertSame(['Bd. Unirii', '12A'], EcoletShipments::splitStreet('Bd. Unirii nr. 12A'));
        $this->assertSame(['Strada 1 Decembrie 1918', '5'], EcoletShipments::splitStreet('Strada 1 Decembrie 1918 nr 5'));
        $this->assertSame('0771280345', EcoletShipments::phone('+40 771 280 345'));
    }

    public function test_auto_send_picks_new_processing_orders(): void
    {
        $this->fakeEcolet();
        Settings::set('ecolet.auto_send', true);
        Settings::set('ecolet.auto_since', now()->subMinute()->toDateTimeString());
        $order = $this->placeOrder();
        $this->artisan('petmarket:ecolet-sync')->assertSuccessful();
        $this->assertSame(4321, (int) $order->fresh()->ecolet_order_to_send_id);
        $this->assertSame('80438360579', $order->fresh()->tracking_number);
    }

    public function test_admin_order_page_shows_ecolet_action(): void
    {
        $order = $this->placeOrder();
        $this->actingAs($this->staff(\App\Enums\Role::SuperAdmin))->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Trimite la Ecolet');
        $this->actingAs($this->staff(\App\Enums\Role::SuperAdmin))->get('/admin/shop-settings')->assertOk()->assertSee('Client Secret');
    }
}
