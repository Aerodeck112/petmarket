@extends('layouts.site')
@section('content')
<div class="wrap page-pad">
    <div class="cart-head">
        <h1 class="display h-lg">Coșul tău</h1>
        <x-buddy class="cart-buddy" kind="dog" pose="peek" :say="\App\Support\Buddies::forPath('cos')['say']" />
    </div>
    <ol class="steps" aria-label="Pașii comenzii"><li aria-current="step">Coș</li><li>Date și livrare</li><li>Confirmare</li></ol>

    @if ($quote->isEmpty())
        <div class="empty">
            <x-buddy kind="cat" pose="sit" :say="['Coșul e gol. L-am verificat cu ochelarii.', 'Hai la cumpărături! Eu vin cu tine.']" />
            <h2 class="h-sm">Coșul tău este gol</h2>
            <p>Hiko și Raza au câteva idei.</p>
            <a class="btn btn-primary" href="{{ site_url('/magazin/') }}">Mergi la magazin</a>
        </div>
    @else
        <div class="cart-grid">
            <form class="cart-lines" method="post" action="{{ site_url('/cos/actualizeaza') }}" x-data x-ref="cartForm">
                @csrf
                @include('shop.partials.freeship')
                <ul>
                    @foreach ($quote->lines as $line)
                        <li class="cart-line">
                            <a class="cart-img" href="{{ site_url($line->product->path()) }}">@if ($line->image())<img src="{{ $line->image() }}" alt="" loading="lazy" width="120" height="120">@endif</a>
                            <div class="cart-info">
                                @if ($line->product->brand)<p class="card-p-brand">{{ $line->product->brand->name }}</p>@endif
                                <a class="cart-name" href="{{ site_url($line->product->path()) }}">{{ $line->product->name }}</a>
                                @if ($line->label())<p class="muted">{{ $line->label() }}</p>@endif
                                <p class="price"><span class="price-now {{ $line->onSale() ? 'is-sale' : '' }}">{{ money($line->unitPrice()) }}</span>@if ($line->onSale())<s class="price-old">{{ money($line->regularUnitPrice()) }}</s>@endif</p>
                                @if (($a = $line->available()) !== null && $a < 5)<p class="stock stock--low"><i></i>Doar {{ $a }} în stoc</p>@endif
                            </div>
                            <div class="qty" role="group" aria-label="Cantitate {{ $line->product->name }}">
                                <button type="button" onclick="this.nextElementSibling.stepDown(); this.form.requestSubmit()" aria-label="Scade"><x-hv-icon name="minus" width="18" height="18" /></button>
                                <input type="number" name="qty[{{ $line->cartItemId }}]" value="{{ $line->qty }}" min="0" max="{{ $line->product->max_qty ?? 999 }}" inputmode="numeric" onchange="this.form.requestSubmit()" aria-label="Cantitate">
                                <button type="button" onclick="this.previousElementSibling.stepUp(); this.form.requestSubmit()" aria-label="Crește"><x-hv-icon name="plus" width="18" height="18" /></button>
                            </div>
                            <p class="cart-sum">{{ money($line->subtotal()) }}</p>
                            <button class="icon-btn" type="submit" formaction="{{ site_url('/cos/sterge/'.$line->cartItemId) }}" aria-label="Scoate din coș"><x-hv-icon name="trash" width="20" height="20" /></button>
                        </li>
                    @endforeach
                </ul>
                <noscript><button class="btn btn-ghost btn-sm" type="submit">Actualizează coșul</button></noscript>
                <p><a href="{{ site_url('/magazin/') }}"><x-hv-icon name="arrow" width="16" height="16" style="transform:scaleX(-1);display:inline" /> Continuă cumpărăturile</a></p>
            </form>

            <aside class="summary">
                <h2 class="h-sm">Sumar comandă</h2>
                <form class="coupon" method="post" action="{{ site_url('/cos/cupon') }}">
                    @csrf
                    @if ($quote->coupon)
                        <p class="coupon-on"><x-hv-icon name="tag" width="18" height="18" /> <b>{{ $quote->coupon->code }}</b> · {{ $quote->coupon->summary() }}
                            <button class="link-btn" type="submit" name="remove" value="1">Elimină</button></p>
                    @else
                        <label class="sr-only" for="cupon">Cod cupon</label>
                        <div class="coupon-row"><input id="cupon" name="code" placeholder="Ai un cupon?" autocomplete="off" required maxlength="60"><button class="btn btn-ghost btn-sm" type="submit">Aplică</button></div>
                    @endif
                </form>
                @include('shop.partials.totals')
                <a class="btn btn-primary btn-block" href="{{ site_url('/finalizare/') }}" data-track="begin_checkout">Finalizează comanda <x-hv-icon name="arrow" width="20" height="20" /></a>
                <ul class="summary-perks"><li><x-hv-icon name="lock" width="18" height="18" /> Datele tale sunt în siguranță</li><li><x-hv-icon name="return" width="18" height="18" /> Retur în 14 zile</li></ul>
            </aside>
        </div>
        @if ($suggestions->isNotEmpty())
            <section class="cart-suggest"><h2 class="h-sm">Merg bine împreună</h2><div class="grid-p grid-p--4">@foreach ($suggestions as $p)@include('shop.partials.card', ['p' => $p])@endforeach</div></section>
        @endif
    @endif
</div>
@endsection
