<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\WishlistItem;
use App\Services\Seo\Seo;
use App\Services\Shop\CartService;
use App\Services\Shop\Counties;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/** „Contul meu”: panou, comenzi, adrese, detalii cont, favorite (ca in WooCommerce). */
class AccountController extends Controller
{
    private function seo(Seo $seo, string $title, string $path): Seo
    {
        $seo->page($title)->crumbs([['Acasă', '/'], ['Contul meu', '/contul-meu/'], ...($path !== '/contul-meu/' ? [[$title, $path]] : [])]);
        $seo->noindex = true;

        return $seo;
    }

    public function dashboard(Request $request, Seo $seo)
    {
        $user = $request->user();
        if ($user->isStaff() && ! $request->boolean('client')) {
            return redirect()->away(site_url('/admin'));
        }

        return view('account.dashboard', ['seo' => $this->seo($seo, 'Contul meu', '/contul-meu/'), 'user' => $user,
            'orders' => $user->orders()->with('items')->limit(3)->get(), 'wishlistCount' => $user->wishlist()->count()]);
    }

    public function orders(Request $request, Seo $seo)
    {
        return view('account.orders', ['seo' => $this->seo($seo, 'Comenzile mele', '/contul-meu/comenzi/'),
            'orders' => $request->user()->orders()->withCount('items')->paginate(15)]);
    }

    public function order(Request $request, Seo $seo, string $number)
    {
        $order = $request->user()->orders()->where('number', $number)->with(['items', 'events' => fn ($q) => $q->where('is_customer_visible', true)])->firstOrFail();

        return view('account.order', ['seo' => $this->seo($seo, 'Comanda '.$order->number, '/contul-meu/comenzi/'.$order->number.'/'), 'order' => $order]);
    }

    /** Comanda din nou: produsele inca disponibile ajung in cos. */
    public function reorder(Request $request, CartService $cart, string $number)
    {
        $order = $request->user()->orders()->where('number', $number)->with('items.product', 'items.variant')->firstOrFail();
        $added = 0;
        foreach ($order->items as $item) {
            try {
                if ($item->product && $item->product->status === 'published') {
                    $cart->add($item->product, $item->variant, $item->qty);
                    $added++;
                }
            } catch (\InvalidArgumentException) {
            }
        }

        return redirect()->away(site_url('/cos/'))->with($added ? 'cart_ok' : 'cart_error', $added ? 'Produsele disponibile au fost adăugate în coș.' : 'Produsele din această comandă nu mai sunt disponibile.');
    }

    public function addresses(Request $request, Seo $seo)
    {
        return view('account.addresses', ['seo' => $this->seo($seo, 'Adrese', '/contul-meu/adrese/'),
            'addresses' => $request->user()->addresses()->orderBy('type')->orderByDesc('is_default')->get(), 'counties' => Counties::ALL]);
    }

    public function saveAddress(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer', 'type' => 'required|in:billing,shipping',
            'first_name' => 'required|string|max:120', 'last_name' => 'required|string|max:120', 'company' => 'nullable|string|max:190',
            'cui' => ['nullable', 'regex:/^(RO)?\d{2,10}$/i'], 'reg_com' => 'nullable|string|max:60', 'phone' => 'nullable|string|max:30',
            'county' => ['required', Rule::in(array_keys(Counties::ALL))], 'city' => 'required|string|max:120',
            'address_1' => 'required|string|max:255', 'address_2' => 'nullable|string|max:255', 'postcode' => 'nullable|regex:/^\d{6}$/',
            'is_default' => 'nullable|boolean',
        ]);
        $user = $request->user();
        $address = ! empty($data['id']) ? $user->addresses()->findOrFail($data['id']) : new Address(['user_id' => $user->id]);
        unset($data['id']);
        $address->fill($data + ['is_default' => false])->save();
        if ($request->boolean('is_default') || $user->addresses()->where('type', $data['type'])->count() === 1) {
            $user->addresses()->where('type', $data['type'])->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        }

        return redirect()->away(site_url('/contul-meu/adrese/'))->with('status', 'Adresa a fost salvată.');
    }

    public function deleteAddress(Request $request, int $address)
    {
        $request->user()->addresses()->whereKey($address)->delete();

        return redirect()->away(site_url('/contul-meu/adrese/'))->with('status', 'Adresa a fost ștearsă.');
    }

    public function details(Request $request, Seo $seo)
    {
        return view('account.details', ['seo' => $this->seo($seo, 'Detalii cont', '/contul-meu/detalii/'), 'user' => $request->user()]);
    }

    public function saveDetails(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => 'required|string|max:120', 'last_name' => 'required|string|max:120', 'phone' => 'nullable|string|max:30',
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => 'nullable|string', 'password' => ['nullable', 'confirmed', PasswordRule::defaults()],
        ]);
        if (! empty($data['password'])) {
            if (! Hash::check((string) $data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Parola actuală nu este corectă.']);
            }
            $user->password = $data['password'];
        }
        $user->fill(['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'name' => $data['first_name'].' '.$data['last_name'],
            'phone' => $data['phone'], 'email' => mb_strtolower($data['email'])])->save();

        return back()->with('status', 'Datele contului au fost salvate.');
    }

    public function wishlist(Request $request, Seo $seo)
    {
        $products = Product::query()->published()->with('mainImage')
            ->whereIn('id', $request->user()->wishlist()->select('product_id'))->get();

        return view('account.wishlist', ['seo' => $this->seo($seo, 'Favorite', '/contul-meu/favorite/'), 'products' => $products]);
    }

    public function toggleWishlist(Request $request)
    {
        $id = $request->validate(['product_id' => 'required|integer|exists:products,id'])['product_id'];
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'login' => site_url('/contul-meu/autentificare/'), 'message' => 'Autentifică-te ca să salvezi produsele favorite.'], 401);
        }
        $existing = WishlistItem::query()->where('user_id', $user->id)->where('product_id', $id)->first();
        $existing ? $existing->delete() : WishlistItem::query()->create(['user_id' => $user->id, 'product_id' => $id]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'saved' => ! $existing, 'count' => $user->wishlist()->count()])
            : back();
    }
}
