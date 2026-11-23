@extends('layouts.site')
@section('content')
@php
    $addr = $address?->toArray() ?? [];
    $bill = $billingAddress?->toArray() ?? $addr;
    $firstMethod = old('shipping_method_id', $methods->first()?->id);
    $firstPay = old('payment_method', $payments[0]->value ?? null);
@endphp
<div class="wrap page-pad" x-data="checkout({ method: {{ (int) $firstMethod }}, payment: @js($firstPay), type: @js(old('customer_type', $bill['company'] ?? null ? 'pj' : 'pf')), county: @js(old('b_county', $bill['county'] ?? 'MS')), shipDifferent: {{ old('ship_different') ? 'true' : 'false' }} })">
    <h1 class="display h-lg">Finalizare comandă</h1>
    <ol class="steps" aria-label="Pașii comenzii"><li><a href="{{ site_url('/cos/') }}">Coș</a></li><li aria-current="step">Date și livrare</li><li>Confirmare</li></ol>
    @guest
        <p class="notice">Ai deja cont? <a href="{{ site_url('/contul-meu/autentificare/') }}">Intră în cont</a> și completăm noi adresa. Poți comanda și fără cont.</p>
    @endguest
    @if ($errors->any())<p class="notice notice--error">Verifică datele marcate mai jos.</p>@endif

    <form class="checkout-grid" method="post" action="{{ site_url('/finalizare') }}" @submit="submitting = true" novalidate>
        @csrf
        <div class="checkout-main">
            <section class="co-step">
                <h2 class="co-title"><span>1</span> Date de contact</h2>
                <div class="seg" role="radiogroup" aria-label="Tip client">
                    <label><input type="radio" name="customer_type" value="pf" x-model="type"> <span>Persoană fizică</span></label>
                    <label><input type="radio" name="customer_type" value="pj" x-model="type"> <span>Persoană juridică</span></label>
                </div>
                <div class="grid-2">
                    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $user?->email) }}" required autocomplete="email" x-model.lazy="email" @change="refresh()">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
                    <div class="field"><label for="phone">Telefon</label><input id="phone" type="tel" name="phone" value="{{ old('phone', $user?->phone ?? ($addr['phone'] ?? '')) }}" required autocomplete="tel" placeholder="07xx xxx xxx">@error('phone')<p class="error">{{ $message }}</p>@enderror<p class="hint">Curierul te sună înainte de livrare.</p></div>
                </div>
                <div class="grid-3" x-show="type === 'pj'" x-cloak>
                    <div class="field"><label for="company">Denumire firmă</label><input id="company" name="company" value="{{ old('company', $bill['company'] ?? '') }}" autocomplete="organization">@error('company')<p class="error">{{ $message }}</p>@enderror</div>
                    <div class="field"><label for="cui">CUI</label><input id="cui" name="cui" value="{{ old('cui', $bill['cui'] ?? '') }}" placeholder="RO12345678">@error('cui')<p class="error">{{ $message }}</p>@enderror</div>
                    <div class="field"><label for="reg_com">Nr. Reg. Com. <span class="muted">(opțional)</span></label><input id="reg_com" name="reg_com" value="{{ old('reg_com', $bill['reg_com'] ?? '') }}" placeholder="J26/123/2020"></div>
                </div>
            </section>

            <section class="co-step">
                <h2 class="co-title"><span>2</span> <span x-text="type === 'pj' ? 'Adresa de facturare' : 'Adresa ta'">Adresa ta</span></h2>
                @include('shop.partials.address-fields', ['p' => 'b_', 'ac' => 'billing', 'values' => $bill, 'shipDifferentAware' => true])
                <label class="check"><input type="checkbox" name="ship_different" value="1" x-model="shipDifferent" @change="county = shipDifferent ? (document.getElementById('s_county').value || county) : document.getElementById('b_county').value; refresh()"> <span>Livrez la o altă adresă</span></label>
                <div x-show="shipDifferent" x-cloak class="co-sub">
                    <h3 class="h-sm">Adresa de livrare</h3>
                    @include('shop.partials.address-fields', ['p' => 's_', 'ac' => 'shipping', 'values' => $addr, 'shipDifferentAware' => true])
                </div>
            </section>

            <section class="co-step">
                <h2 class="co-title"><span>3</span> Livrare</h2>
                <div class="choices">
                    @foreach ($methods as $m)
                        <label class="choice-card" :class="{ 'is-on': method === {{ $m->id }} }">
                            <input type="radio" name="shipping_method_id" value="{{ $m->id }}" x-model.number="method" @change="refresh()" @checked((int) $firstMethod === $m->id)>
                            <x-hv-icon :name="$m->kind === 'pickup' ? 'home' : ($m->kind === 'locker' ? 'box' : 'truck')" width="26" height="26" />
                            <span><b>{{ $m->name }}</b><small>{{ $m->description }}@if ($m->eta) · {{ $m->eta }}@endif</small></span>
                            <em>{{ $m->cost === 0 ? 'gratuit' : money($m->cost) }}</em>
                        </label>
                    @endforeach
                </div>
                @error('shipping_method_id')<p class="error">{{ $message }}</p>@enderror
            </section>

            <section class="co-step">
                <h2 class="co-title"><span>4</span> Plată</h2>
                <div class="choices">
                    @foreach ($payments as $pm)
                        <label class="choice-card" :class="{ 'is-on': payment === '{{ $pm->value }}' }">
                            <input type="radio" name="payment_method" value="{{ $pm->value }}" x-model="payment" @change="refresh()" @checked($firstPay === $pm->value)>
                            <x-hv-icon :name="$pm->value === 'cod' ? 'cash' : ($pm->value === 'card' ? 'card' : 'lock')" width="26" height="26" />
                            <span><b>{{ $pm->label() }}</b><small>@switch($pm->value)
                                @case('cod')Plătești numerar curierului, la primirea coletului.@break
                                @case('transfer'){{ \App\Support\Settings::get('payments.transfer_note') }}@break
                                @default Plată securizată cu cardul.@endswitch</small></span>
                        </label>
                    @endforeach
                </div>
                @error('payment_method')<p class="error">{{ $message }}</p>@enderror
            </section>

            <section class="co-step">
                <h2 class="co-title"><span>5</span> Ultimii pași</h2>
                <div class="field"><label for="note">Mențiuni pentru comandă <span class="muted">(opțional)</span></label><textarea id="note" name="note" rows="3" maxlength="1000" placeholder="De exemplu: sunați la interfon 12.">{{ old('note') }}</textarea></div>
                @guest
                    <label class="check"><input type="checkbox" name="create_account" value="1" x-model="createAccount" @checked(old('create_account'))> <span>Creează-mi un cont, ca să urmăresc comenzile și să comand mai repede data viitoare</span></label>
                    <div class="field" x-show="createAccount" x-cloak><label for="password">Alege o parolă</label><input id="password" type="password" name="password" minlength="10" autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror<p class="hint">Minimum 10 caractere, cu litere și cifre.</p></div>
                @endguest
                <label class="check"><input type="checkbox" name="newsletter" value="1" @checked(old('newsletter'))> <span>Vreau să primesc oferte și sfaturi pe email</span></label>
                <label class="check"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))> <span>Am citit și accept <a href="{{ site_url('/termeni-conditii/') }}" target="_blank">termenii și condițiile</a> și <a href="{{ site_url('/politica-confidentialitate/') }}" target="_blank">politica de confidențialitate</a>.</span></label>
                @error('terms')<p class="error">{{ $message }}</p>@enderror
            </section>
        </div>

        <aside class="summary summary--sticky">
            <h2 class="h-sm">Comanda ta</h2>
            <ul class="sum-lines">
                @foreach ($quote->lines as $line)
                    <li><span class="sum-img">@if ($line->image())<img src="{{ $line->image() }}" alt="" loading="lazy">@endif<b>{{ $line->qty }}</b></span>
                        <span class="sum-name">{{ $line->product->name }}@if ($line->label())<small>{{ $line->label() }}</small>@endif</span><span>{{ money($line->total()) }}</span></li>
                @endforeach
            </ul>
            <div x-ref="totals" aria-live="polite">@include('shop.partials.totals')</div>
            <button class="btn btn-primary btn-block btn-buy" type="submit" :disabled="submitting">
                <x-hv-icon name="lock" width="20" height="20" /> <span x-text="submitting ? 'Se trimite…' : 'Plasează comanda'">Plasează comanda</span>
            </button>
            <p class="muted small">Comanda implică obligația de plată. Factura se emite la livrare.</p>
            <x-buddy class="co-buddy" kind="cat" pose="sit" :say="\App\Support\Buddies::forPath('finalizare')['say']" />
        </aside>
    </form>
</div>
@endsection
