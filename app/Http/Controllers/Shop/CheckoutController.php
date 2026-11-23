<?php

namespace App\Http\Controllers\Shop;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Seo\Seo;
use App\Services\Shop\CartService;
use App\Services\Shop\Counties;
use App\Services\Shop\OrderService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private OrderService $orders) {}

    /** @return list<PaymentMethod> */
    public static function paymentMethods(): array
    {
        return array_values(array_filter([
            Settings::get('payments.cod_enabled') ? PaymentMethod::Cod : null,
            Settings::get('payments.card_enabled') && config('services.stripe.secret') ? PaymentMethod::Card : null,
            Settings::get('payments.transfer_enabled') ? PaymentMethod::Transfer : null,
        ]));
    }

    public function show(Seo $seo)
    {
        $seo->page('Finalizare comandă')->crumbs([['Acasă', '/'], ['Coș', '/cos/'], ['Finalizare comandă', '/finalizare-comanda/']]);
        $seo->noindex = true;
        $quote = $this->cart->quote();
        if ($quote->isEmpty()) {
            return redirect()->away(site_url('/cos/'));
        }
        $user = auth()->user();
        $methods = ShippingMethod::query()->where('is_active', true)->orderBy('sort')->get();

        return view('shop.checkout', [
            'seo' => $seo, 'quote' => $quote, 'methods' => $methods, 'payments' => self::paymentMethods(), 'counties' => Counties::ALL,
            'user' => $user, 'address' => $user?->addresses()->where('type', 'shipping')->orderByDesc('is_default')->first(),
            'billingAddress' => $user?->addresses()->where('type', 'billing')->orderByDesc('is_default')->first(),
            'methodsJson' => $methods->map(fn ($m) => ['id' => $m->id, 'cost' => $m->cost, 'free_over' => $m->free_over, 'cod' => $m->cod_allowed, 'counties' => $m->counties])->values(),
            'threshold' => (int) Settings::get('commerce.free_shipping_threshold'),
        ]);
    }

    /** Totalurile recalculate la schimbarea livrarii / platii (JSON, fara a crea nimic). */
    public function preview(Request $request)
    {
        $method = ShippingMethod::query()->where('is_active', true)->find($request->integer('shipping_method_id'));
        $payment = PaymentMethod::tryFrom((string) $request->input('payment_method'));
        $quote = $this->cart->quote($method, $payment, $request->input('email'), $request->input('county'));

        return response()->json(['html' => view('shop.partials.totals', ['quote' => $quote])->render(), 'problems' => $quote->problems, 'total' => money($quote->total)]);
    }

    public function place(Request $request)
    {
        $pj = $request->input('customer_type') === 'pj';
        $shipDifferent = $request->boolean('ship_different');
        $address = fn (string $p) => [
            $p.'first_name' => 'required|string|max:120', $p.'last_name' => 'required|string|max:120',
            $p.'county' => ['required', Rule::in(array_keys(Counties::ALL))], $p.'city' => 'required|string|max:120',
            $p.'address_1' => 'required|string|max:255', $p.'address_2' => 'nullable|string|max:255', $p.'postcode' => 'nullable|regex:/^\d{6}$/',
        ];
        $rules = array_merge([
            'customer_type' => 'required|in:pf,pj',
            'email' => 'required|email:rfc|max:190',
            'phone' => ['required', 'regex:/^(\+4|004)?0?7\d{8}$|^(\+4|004)?0[23]\d{8}$/'],
            'company' => $pj ? 'required|string|max:190' : 'nullable',
            'cui' => $pj ? ['required', 'regex:/^(RO)?\d{2,10}$/i'] : 'nullable',
            'reg_com' => 'nullable|string|max:60',
            'shipping_method_id' => ['required', Rule::exists('shipping_methods', 'id')->where('is_active', true)],
            'payment_method' => ['required', Rule::in(array_map(fn ($p) => $p->value, self::paymentMethods()))],
            'note' => 'nullable|string|max:1000',
            'terms' => 'accepted',
            'create_account' => 'nullable|boolean',
            'password' => $request->boolean('create_account') && ! auth()->check() ? 'required|string|min:10' : 'nullable',
        ], $address('b_'), $shipDifferent ? $address('s_') : []);

        $data = $request->validate($rules, [
            'phone.regex' => 'Scrie un număr de telefon românesc valid (de exemplu 07xx xxx xxx).',
            'cui.regex' => 'CUI-ul trebuie să conțină doar cifre, opțional cu prefixul RO.',
            'b_postcode.regex' => 'Codul poștal are 6 cifre.', 's_postcode.regex' => 'Codul poștal are 6 cifre.',
            'terms.accepted' => 'Trebuie să accepți termenii și condițiile pentru a plasa comanda.',
        ]);

        $pick = fn (string $p) => [
            'first_name' => $data[$p.'first_name'], 'last_name' => $data[$p.'last_name'], 'county' => Counties::name($data[$p.'county']),
            'county_code' => $data[$p.'county'], 'city' => $data[$p.'city'], 'address_1' => $data[$p.'address_1'],
            'address_2' => $data[$p.'address_2'] ?? null, 'postcode' => $data[$p.'postcode'] ?? null, 'country' => 'RO',
        ];
        $billing = $pick('b_') + ['company' => $pj ? $data['company'] : null, 'cui' => $pj ? strtoupper($data['cui']) : null, 'reg_com' => $pj ? ($data['reg_com'] ?? null) : null, 'phone' => $data['phone'], 'email' => $data['email']];
        $shipping = ($shipDifferent ? $pick('s_') : $pick('b_')) + ['phone' => $data['phone']];

        $user = $request->user();
        if (! $user && $request->boolean('create_account')) {
            if (User::query()->where('email', mb_strtolower($data['email']))->exists()) {
                return back()->withInput()->withErrors(['email' => 'Există deja un cont cu acest email. Autentifică-te sau continuă fără cont.']);
            }
            $user = User::query()->create(['name' => $billing['first_name'].' '.$billing['last_name'], 'first_name' => $billing['first_name'],
                'last_name' => $billing['last_name'], 'email' => mb_strtolower($data['email']), 'phone' => $data['phone'], 'password' => $data['password']]);
            $user->assignRole('customer');
        }
        if (! $user && ! Settings::get('commerce.guest_checkout')) {
            return back()->withInput()->withErrors(['email' => 'Pentru a comanda trebuie să ai cont.']);
        }

        try {
            $order = $this->orders->place($this->cart->lines(), $this->cart->current()?->coupon_code, ShippingMethod::query()->findOrFail($data['shipping_method_id']),
                PaymentMethod::from($data['payment_method']), [
                    'email' => $data['email'], 'phone' => $data['phone'], 'customer_type' => $data['customer_type'],
                    'billing' => $billing, 'shipping' => $shipping, 'note' => $data['note'] ?? null, 'marketing' => $request->boolean('newsletter'),
                ], $user);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('cart_error', $e->getMessage());
        }

        $this->cart->clear();
        if ($user) {
            $this->rememberAddresses($user, $billing, $shipping, $pj);
            if (! Auth::check()) {
                Auth::login($user);
                $request->session()->regenerate();
            }
        }
        if ($request->boolean('newsletter')) {
            NewsletterSubscriber::query()->firstOrCreate(['email' => mb_strtolower($data['email'])], ['name' => $billing['first_name'], 'source' => 'checkout', 'token' => Str::random(40)]);
        }
        $request->session()->put('last_order', $order->id);

        return redirect()->away($order->publicUrl());
    }

    public function received(Seo $seo, string $token)
    {
        $order = Order::query()->where('token', $token)->with(['items', 'events' => fn ($q) => $q->where('is_customer_visible', true)])->firstOrFail();
        $seo->page('Comanda '.$order->number);
        $seo->noindex = true;
        $fresh = session('last_order') === $order->id;

        return view('shop.order-received', ['seo' => $seo, 'order' => $order, 'fresh' => $fresh]);
    }

    private function rememberAddresses(User $user, array $billing, array $shipping, bool $pj): void
    {
        $map = fn (array $a) => ['first_name' => $a['first_name'], 'last_name' => $a['last_name'], 'county' => $a['county_code'], 'city' => $a['city'],
            'address_1' => $a['address_1'], 'address_2' => $a['address_2'], 'postcode' => $a['postcode'], 'phone' => $a['phone'] ?? null];
        if (! $user->addresses()->where('type', 'shipping')->exists()) {
            $user->addresses()->create($map($shipping) + ['type' => 'shipping', 'is_default' => true]);
        }
        if (! $user->addresses()->where('type', 'billing')->exists()) {
            $user->addresses()->create($map($billing) + ['type' => 'billing', 'is_default' => true,
                'company' => $pj ? $billing['company'] : null, 'cui' => $pj ? $billing['cui'] : null, 'reg_com' => $pj ? $billing['reg_com'] : null]);
        }
    }
}
