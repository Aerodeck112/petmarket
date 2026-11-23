<?php

namespace Tests;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Shop\Line;
use App\Services\Shop\OrderService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShopSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testele golesc baza de date la fiecare rulare (RefreshDatabase). Refuza orice baza care nu este clar de test,
     * ca o rulare cu setarile unui server sau ale unei copii de proba sa nu poata sterge date.
     */
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");
        if ($database !== ':memory:' && ! preg_match('/(^|_)test(ing)?(_|$)/i', basename($database))) {
            throw new \RuntimeException("Testele refuză să ruleze pe baza „{$database}” ({$connection}): nu este o bază de test.");
        }

        return $app;
    }

    /** Date de baza: roluri, metode de livrare, pagini, bannere; disc privat fals. */
    protected function seedShop(): void
    {
        $this->seed([RolesSeeder::class, ShopSeeder::class]);
        Storage::fake('private');
    }

    /** Pastrat pentru testele de infrastructura preluate din HiKeVet. */
    protected function seedBusiness(): void
    {
        $this->seedShop();
    }

    protected function staff(Role $role = Role::ShopManager): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    protected function customer(): User
    {
        $user = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pop']);
        $user->assignRole(Role::Customer->value);

        return $user;
    }

    protected function product(array $attrs = []): Product
    {
        $name = $attrs['name'] ?? 'Hrană test '.Str::random(5);

        return Product::query()->create(array_merge([
            'type' => 'simple', 'status' => 'published', 'name' => $name, 'slug' => Str::slug($name),
            'regular_price' => 10000, 'manage_stock' => true, 'stock_qty' => 10,
        ], $attrs));
    }

    protected function checkoutData(array $overrides = []): array
    {
        return array_merge([
            'customer_type' => 'pf', 'email' => 'client.test@example.com', 'phone' => '0722123456',
            'b_first_name' => 'Ana', 'b_last_name' => 'Pop', 'b_county' => 'MS', 'b_city' => 'Târgu Mureș', 'b_address_1' => 'Str. Test 1',
            'shipping_method_id' => ShippingMethod::query()->where('code', 'curier')->value('id'), 'payment_method' => 'cod', 'terms' => '1',
        ], $overrides);
    }

    /** O comanda plasata direct prin serviciu (fara HTTP). */
    protected function placeOrder(?Product $product = null, int $qty = 1, PaymentMethod $payment = PaymentMethod::Cod): Order
    {
        $product ??= $this->product();
        $address = ['first_name' => 'Ana', 'last_name' => 'Pop', 'county' => 'Mureș', 'county_code' => 'MS', 'city' => 'Târgu Mureș', 'address_1' => 'Str. Test 1', 'country' => 'RO'];

        return app(OrderService::class)->place([new Line($product, null, $qty)], null, ShippingMethod::query()->where('code', 'curier')->firstOrFail(), $payment,
            ['email' => 'client.test@example.com', 'phone' => '0722123456', 'customer_type' => 'pf', 'billing' => $address, 'shipping' => $address, 'note' => null, 'marketing' => false]);
    }
}
